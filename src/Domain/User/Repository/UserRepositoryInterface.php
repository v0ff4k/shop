<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;

/**
 * Интерфейс репозитория пользователей.
 */
interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findByUsername(string $username): ?User;

    public function findOneByUsernameOrEmail(string $usernameOrEmail): ?User;

    public function findAll(int $limit = 50, int $offset = 0): array;

    public function findByRole(string $role, int $limit = 50, int $offset = 0): array;

    public function findRegisteredByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(User $user): void;

    public function remove(User $user): void;

    public function count(): int;

    public function countByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int;
}
