<?php

declare(strict_types=1);

namespace Tests\Domain\Shop\ValueObject;

use App\Domain\Shop\ValueObject\CategoryId;
use PHPUnit\Framework\TestCase;

class CategoryIdTest extends TestCase
{
    public function testCreationFromValidId(): void
    {
        $id = CategoryId::fromInt(123);

        $this->assertInstanceOf(CategoryId::class, $id);
        $this->assertSame(123, $id->value());
    }

    public function testCreationFromInvalidIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CategoryId::fromInt(0);
    }

    public function testEquality(): void
    {
        $id1 = CategoryId::fromInt(1);
        $id2 = CategoryId::fromInt(1);
        $id3 = CategoryId::fromInt(2);

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testStringRepresentation(): void
    {
        $id = CategoryId::fromInt(42);

        $this->assertSame('42', (string) $id);
    }
}
