<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * DTO для запроса создания/обновления продукта.
 */
final class ProductCreateRequest
{
    public function __construct(
        public readonly string $title,
        public readonly float $price,
        public readonly string $description,
        public readonly int $categoryId,
        public readonly int $available = 0,
        public readonly ?array $images = null
    ) {
    }
}
