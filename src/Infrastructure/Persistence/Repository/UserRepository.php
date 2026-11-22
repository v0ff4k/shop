<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

/**
 * Реализация репозитория пользователей (Doctrine ORM).
 */
final class UserRepository implements UserRepositoryInterface
{
    private ObjectRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->repository = $entityManager->getRepository(User::class);
    }

    public function findById(UserId $id): ?User
    {
        return $this->repository->find($id->value());
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email->value()]);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->repository->findOneBy(['username' => $username]);
    }

    public function findOneByUsernameOrEmail(string $usernameOrEmail): ?User
    {
        return $this->repository->createQueryBuilder('u')
            ->where('u.username = :query OR u.email = :query')
            ->setParameter('query', $usernameOrEmail)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        return $this->repository->findBy([], ['registeredAt' => 'DESC'], $limit, $offset);
    }

    public function findByRole(string $role, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->where(':role MEMBER OF u.roles')
            ->setParameter('role', $role)
            ->orderBy('u.registeredAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function findRegisteredByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.registeredAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('u.registeredAt', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function remove(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function count(): int
    {
        return $this->repository->count([]);
    }

    /**
     * temp unused.
     */
    public function countByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.registeredAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
