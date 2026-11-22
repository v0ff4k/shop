<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop\ValueObject;

use App\Domain\Shop\ValueObject\Slug;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для ValueObject Slug.
 */
final class SlugTest extends TestCase
{
    public function testCreateFromString(): void
    {
        $slug = Slug::fromString('my-product-slug');

        $this->assertSame('my-product-slug', $slug->value());
        $this->assertSame('my-product-slug', (string) $slug);
    }

    public function testGenerateFromTitle(): void
    {
        $slug = Slug::generate('My Product Title');

        $this->assertSame('my-product-title', $slug->value());
    }

    public function testGenerateWithSpecialCharacters(): void
    {
        $slug = Slug::generate('Product with special @#$ characters!');

        $this->assertSame('product-with-special-characters', $slug->value());
    }

    public function testGenerateWithRussianText(): void
    {
        $slug = Slug::generate('Товар на русском');

        $this->assertIsString($slug->value());
    }

    public function testEquals(): void
    {
        $slug1 = Slug::fromString('my-slug');
        $slug2 = Slug::fromString('my-slug');
        $slug3 = Slug::fromString('other-slug');

        $this->assertTrue($slug1->equals($slug2));
        $this->assertFalse($slug1->equals($slug3));
    }

    public function testInvalidSlugFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slug format');

        Slug::fromString('invalid_slug_with_underscore');
    }

    public function testSlugTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slug must not exceed 255 characters');

        Slug::fromString(str_repeat('a', 256));
    }

    public function testSlugWithNumbers(): void
    {
        $slug = Slug::fromString('product-123');

        $this->assertSame('product-123', $slug->value());
    }

    public function testSlugWithMultipleDashes(): void
    {
        $slug = Slug::fromString('my-long-product-name');

        $this->assertSame('my-long-product-name', $slug->value());
    }
}
