<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\AddToCartRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Тесты для AddToCartRequestDto.
 */
final class AddToCartRequestDtoTest extends TestCase
{
    public function testCreateFromValidRequest(): void
    {
        $request = new Request(
            [],
            ['product_id' => '123', 'quantity' => '5']
        );

        $dto = AddToCartRequestDto::fromRequest($request);

        $this->assertSame(123, $dto->productId);
        $this->assertSame(5, $dto->quantity);
    }

    public function testCreateFromRequestWithDefaults(): void
    {
        $request = new Request(
            [],
            ['product_id' => '42']
        );

        $dto = AddToCartRequestDto::fromRequest($request);

        $this->assertSame(42, $dto->productId);
        $this->assertSame(1, $dto->quantity);
    }

    public function testCreateFromJson(): void
    {
        $data = [
            'product_id' => 99,
            'quantity' => 10,
        ];

        $dto = AddToCartRequestDto::fromJson($data);

        $this->assertSame(99, $dto->productId);
        $this->assertSame(10, $dto->quantity);
    }

    public function testQuantityMinimumIsOne(): void
    {
        $request = new Request(
            [],
            ['product_id' => '1', 'quantity' => '0']
        );

        $dto = AddToCartRequestDto::fromRequest($request);

        $this->assertSame(1, $dto->quantity);
    }

    public function testCreateFromJsonWithMissingData(): void
    {
        $data = [];

        $dto = AddToCartRequestDto::fromJson($data);

        $this->assertSame(0, $dto->productId);
        $this->assertSame(1, $dto->quantity);
    }
}
