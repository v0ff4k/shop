<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\ProductSuggestRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ProductSuggestRequestDtoTest extends TestCase
{
    public function testCreateFromRequest(): void
    {
        $dto = ProductSuggestRequestDto::fromRequest(new Request([
            'q' => '  plastic  ',
            'limit' => '5',
        ]));

        $this->assertSame('plastic', $dto->query);
        $this->assertSame(5, $dto->limit);
    }

    public function testLimitIsClamped(): void
    {
        $dto = ProductSuggestRequestDto::fromRequest(new Request([
            'q' => 'plastic',
            'limit' => '999',
        ]));

        $this->assertSame(10, $dto->limit);
    }
}
