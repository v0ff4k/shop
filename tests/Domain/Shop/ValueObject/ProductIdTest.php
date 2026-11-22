<?php

declare(strict_types=1);

namespace Tests\Domain\Shop\ValueObject;

use App\Domain\Shop\ValueObject\ProductId;
use PHPUnit\Framework\TestCase;

class ProductIdTest extends TestCase
{
    public function testCreationFromValidId(): void
    {
        $id = ProductId::fromInt(123);

        $this->assertInstanceOf(ProductId::class, $id);
        $this->assertSame(123, $id->value());
    }

    public function testCreationFromInvalidIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProductId::fromInt(0);
    }

    public function testEquality(): void
    {
        $id1 = ProductId::fromInt(1);
        $id2 = ProductId::fromInt(1);
        $id3 = ProductId::fromInt(2);

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testStringRepresentation(): void
    {
        $id = ProductId::fromInt(42);

        $this->assertSame('42', (string) $id);
    }
}
