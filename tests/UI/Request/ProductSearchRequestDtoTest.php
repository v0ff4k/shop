<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\ProductSearchRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Тесты для ProductSearchRequestDto.
 */
final class ProductSearchRequestDtoTest extends TestCase
{
    public function testCreateFromValidRequest(): void
    {
        $request = new Request([
            'q' => 'test query',
            'category_id' => '5',
            'min_price' => '100',
            'max_price' => '1000',
            'page' => '2',
            'limit' => '50',
            'sort_by' => 'price',
            'sort_order' => 'asc',
        ]);

        $dto = ProductSearchRequestDto::fromRequest($request);

        $this->assertSame('test query', $dto->query);
        $this->assertSame(5, $dto->categoryId);
        $this->assertSame(100.0, $dto->minPrice);
        $this->assertSame(1000.0, $dto->maxPrice);
        $this->assertSame(2, $dto->page);
        $this->assertSame(50, $dto->limit);
        $this->assertSame('price', $dto->sortBy);
        $this->assertSame('asc', $dto->sortOrder);
    }

    public function testCreateFromRequestWithDefaults(): void
    {
        $request = new Request();

        $dto = ProductSearchRequestDto::fromRequest($request);

        $this->assertNull($dto->query);
        $this->assertNull($dto->categoryId);
        $this->assertNull($dto->minPrice);
        $this->assertNull($dto->maxPrice);
        $this->assertSame(1, $dto->page);
        $this->assertSame(20, $dto->limit);
        $this->assertSame('added', $dto->sortBy);
        $this->assertSame('desc', $dto->sortOrder);
    }

    public function testGetOffset(): void
    {
        $request = new Request(['page' => '3', 'limit' => '25']);
        $dto = ProductSearchRequestDto::fromRequest($request);

        $this->assertSame(50, $dto->getOffset());
    }

    public function testHasQuery(): void
    {
        $requestWithQuery = new Request(['q' => 'test']);
        $dtoWithQuery = ProductSearchRequestDto::fromRequest($requestWithQuery);
        $this->assertTrue($dtoWithQuery->hasQuery());

        $requestWithEmptyQuery = new Request(['q' => '  ']);
        $dtoWithEmptyQuery = ProductSearchRequestDto::fromRequest($requestWithEmptyQuery);
        $this->assertFalse($dtoWithEmptyQuery->hasQuery());

        $requestWithoutQuery = new Request();
        $dtoWithoutQuery = ProductSearchRequestDto::fromRequest($requestWithoutQuery);
        $this->assertFalse($dtoWithoutQuery->hasQuery());
    }

    public function testHasCategoryFilter(): void
    {
        $requestWithCategory = new Request(['category_id' => '10']);
        $dtoWithCategory = ProductSearchRequestDto::fromRequest($requestWithCategory);
        $this->assertTrue($dtoWithCategory->hasCategoryFilter());

        $requestWithAllCategories = new Request(['category_id' => '0']);
        $dtoWithAllCategories = ProductSearchRequestDto::fromRequest($requestWithAllCategories);
        $this->assertNull($dtoWithAllCategories->categoryId);
        $this->assertFalse($dtoWithAllCategories->hasCategoryFilter());

        $requestWithEmptyCategory = new Request(['category_id' => '']);
        $dtoWithEmptyCategory = ProductSearchRequestDto::fromRequest($requestWithEmptyCategory);
        $this->assertNull($dtoWithEmptyCategory->categoryId);
        $this->assertFalse($dtoWithEmptyCategory->hasCategoryFilter());

        $requestWithoutCategory = new Request();
        $dtoWithoutCategory = ProductSearchRequestDto::fromRequest($requestWithoutCategory);
        $this->assertFalse($dtoWithoutCategory->hasCategoryFilter());
    }

    public function testHasPriceFilter(): void
    {
        $requestWithMinPrice = new Request(['min_price' => '100']);
        $dtoWithMinPrice = ProductSearchRequestDto::fromRequest($requestWithMinPrice);
        $this->assertTrue($dtoWithMinPrice->hasPriceFilter());

        $requestWithMaxPrice = new Request(['max_price' => '1000']);
        $dtoWithMaxPrice = ProductSearchRequestDto::fromRequest($requestWithMaxPrice);
        $this->assertTrue($dtoWithMaxPrice->hasPriceFilter());

        $requestWithoutPrice = new Request();
        $dtoWithoutPrice = ProductSearchRequestDto::fromRequest($requestWithoutPrice);
        $this->assertFalse($dtoWithoutPrice->hasPriceFilter());
    }

    public function testPageLimits(): void
    {
        $request = new Request(['page' => '-5', 'limit' => '500']);
        $dto = ProductSearchRequestDto::fromRequest($request);

        $this->assertSame(1, $dto->page);
        $this->assertSame(100, $dto->limit);
    }
}
