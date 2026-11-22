<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * DTO для пагинированного ответа.
 *
 * @template T
 */
final class PaginatedResponse
{
    /**
     * @param array<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
        public readonly int $pages
    ) {
    }

    /**
     * @param array<T> $items
     *
     * @return PaginatedResponse<T>
     */
    public static function create(array $items, int $total, int $page, int $limit): self
    {
        return new self(
            $items,
            $total,
            $page,
            $limit,
            (int) ceil($total / $limit)
        );
    }
}
