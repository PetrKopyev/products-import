<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Entity\Product;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class ProductRepository
{
    /** @var EntityRepository<Product> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManager $em)
    {
        $this->repository = $em->getRepository(Product::class);
    }

    public function findById(int $id): ?Product
    {
        return $this->repository->find($id);
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->repository->findOneBy(['externalCode' => $externalCode]);
    }

    public function save(Product $product): void
    {
        $this->em->persist($product);
    }

    public function remove(Product $product): void
    {
        $this->em->remove($product);
    }

    /**
     * Серверная пагинация + фильтрация по name и диапазону цены.
     *
     * @param array{name?: string, price_min?: float|null, price_max?: float|null} $filters
     * @return array{items: array<Product>, total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.id', 'ASC');

        if (isset($filters['name']) && $filters['name'] !== '') {
            $qb->andWhere('LOWER(p.name) LIKE LOWER(:name)')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (isset($filters['price_min'])) {
            $qb->andWhere('p.price >= :price_min')
                ->setParameter('price_min', number_format($filters['price_min'], 2, '.', ''));
        }

        if (isset($filters['price_max'])) {
            $qb->andWhere('p.price <= :price_max')
                ->setParameter('price_max', number_format($filters['price_max'], 2, '.', ''));
        }

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        /** @var array<Product> $items */
        $items = iterator_to_array($paginator->getIterator());

        return ['items' => array_values($items), 'total' => \count($paginator)];
    }

    public function countAll(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
