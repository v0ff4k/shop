<?php

declare(strict_types=1);

namespace App\Domain\Order\Repository;

use App\Domain\Order\Entity\Order;
use App\Domain\User\ValueObject\UserId;

/**
 * Интерфейс репозитория заказов.
 */
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    public function findByUser(UserId $userId): array;

    public function findAll(int $limit = 50, int $offset = 0): array;

    public function findSalesByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(Order $order): void;

    public function remove(Order $order): void;

    public function count(): int;

    public function countByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int;

    public function getTotalSalesByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): float;
}
