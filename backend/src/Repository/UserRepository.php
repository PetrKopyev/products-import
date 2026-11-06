<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

final class UserRepository
{
    /** @var EntityRepository<User> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManager $em)
    {
        $this->repository = $em->getRepository(User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email]);
    }

    public function findById(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
