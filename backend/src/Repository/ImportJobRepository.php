<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Entity\ImportJob;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

final class ImportJobRepository
{
    /** @var EntityRepository<ImportJob> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManager $em)
    {
        $this->repository = $em->getRepository(ImportJob::class);
    }

    public function findById(int $id): ?ImportJob
    {
        return $this->repository->find($id);
    }

    public function findLatest(): ?ImportJob
    {
        /** @var ImportJob|null */
        return $this->repository->findOneBy([], ['id' => 'DESC']);
    }

    public function save(ImportJob $job): void
    {
        $this->em->persist($job);
        $this->em->flush();
    }
}
