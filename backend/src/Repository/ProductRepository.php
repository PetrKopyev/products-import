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
     * Серверная пагинация + фильтрация по name и диапазону цены + сортировка.
     *
     * @param array{name?: string, price_min?: float|null, price_max?: float|null, sort?: string} $filters
     * @return array{items: array<Product>, total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p');

        if (($filters['sort'] ?? '') === 'price_asc') {
            $qb->orderBy('p.price', 'ASC')->addOrderBy('p.id', 'ASC');
        } elseif (($filters['sort'] ?? '') === 'price_desc') {
            $qb->orderBy('p.price', 'DESC')->addOrderBy('p.id', 'ASC');
        } else {
            // По умолчанию — новые товары первыми (свежие импорты видно сразу)
            $qb->orderBy('p.id', 'DESC');
        }

        if (isset($filters['name']) && $filters['name'] !== '') {
            // Регистронезависимый поиск для любых языков: SQLite LOWER() умеет только латиницу,
            // поэтому сравниваем с вариантами шаблона в разных регистрах
            $pattern = $filters['name'];
            $qb->andWhere(
                'p.name LIKE :name_exact OR p.name LIKE :name_lower'
                . ' OR p.name LIKE :name_upper OR p.name LIKE :name_title',
            )
                ->setParameter('name_exact', '%' . $pattern . '%')
                ->setParameter('name_lower', '%' . mb_strtolower($pattern, 'UTF-8') . '%')
                ->setParameter('name_upper', '%' . mb_strtoupper($pattern, 'UTF-8') . '%')
                ->setParameter('name_title', '%' . mb_convert_case($pattern, MB_CASE_TITLE, 'UTF-8') . '%');
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
