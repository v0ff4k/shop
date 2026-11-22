<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Shop\Entity\ProductImage;

/**
 * DTO для ответа изображения продукта.
 */
final class ProductImageResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $path,
        public readonly ?string $alt,
        public readonly int $position,
        public readonly \DateTimeImmutable $uploadedAt
    ) {
    }

    public static function fromEntity(ProductImage $image): self
    {
        return new self(
            $image->getId(),
            $image->getPath(),
            $image->getAlt(),
            $image->getPosition(),
            $image->getUploadedAt()
        );
    }
}
