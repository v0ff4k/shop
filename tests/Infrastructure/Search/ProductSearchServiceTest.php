<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Search;

use App\Infrastructure\Search\ProductSearchService;
use Elastica\Client;
use Elastica\Index;
use Elastica\Query;
use Elastica\ResultSet;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductSearchServiceTest extends TestCase
{
    private Client&MockObject $elasticaClient;
    private Index&MockObject $index;
    private ProductSearchService $searchService;

    protected function setUp(): void
    {
        $this->elasticaClient = $this->createMock(Client::class);
        $this->index = $this->createMock(Index::class);
        $resultSet = $this->createMock(ResultSet::class);

        $this->elasticaClient
            ->method('getIndex')
            ->with('products')
            ->willReturn($this->index);

        $this->index
            ->method('search')
            ->willReturn($resultSet);

        $resultSet
            ->method('getTotalHits')
            ->willReturn(0);

        $resultSet
            ->method('getResults')
            ->willReturn([]);

        $this->searchService = new ProductSearchService($this->elasticaClient);
    }

    public function testSearchWithQuery(): void
    {
        $capturedQuery = null;

        $this->index
            ->expects($this->once())
            ->method('search')
            ->with($this->callback(static function (Query $query) use (&$capturedQuery): bool {
                $capturedQuery = $query->toArray();

                return true;
            }));

        $result = $this->searchService->search('test product');

        $this->assertIsArray($capturedQuery);
        $this->assertSame(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => 'test product',
                                    'fields' => ['title^2', 'description'],
                                    'fuzziness' => 0.5,
                                    'type' => 'best_fields',
                                ],
                            ],
                        ],
                    ],
                ],
                'from' => 0,
                'size' => 20,
                'sort' => [
                    '_score' => [
                        'order' => 'desc',
                    ],
                ],
            ],
            $capturedQuery
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(20, $result['perPage']);
    }

    public function testSearchWithCategoryFilter(): void
    {
        $capturedQuery = null;

        $this->index
            ->expects($this->once())
            ->method('search')
            ->with($this->callback(static function (Query $query) use (&$capturedQuery): bool {
                $capturedQuery = $query->toArray();

                return true;
            }));

        $result = $this->searchService->search('', 5);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total']);
        $this->assertArrayHasKey('query', $capturedQuery);
        $this->assertArrayHasKey('bool', $capturedQuery['query']);
        $this->assertArrayHasKey('filter', $capturedQuery['query']['bool']);
        $this->assertArrayNotHasKey('must', $capturedQuery['query']['bool']);
    }

    public function testSearchWithCategoryAndPriceFiltersWithoutQuery(): void
    {
        $capturedQuery = null;

        $this->index
            ->expects($this->once())
            ->method('search')
            ->with($this->callback(static function (Query $query) use (&$capturedQuery): bool {
                $capturedQuery = $query->toArray();

                return true;
            }));

        $result = $this->searchService->search('', 5, 50.0, 200.0, false, 1, 20, 'price', 'asc');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total']);
        $this->assertSame(
            [
                [
                    'term' => [
                        'category_id' => [
                            'value' => 5,
                            'boost' => 1.0,
                        ],
                    ],
                ],
                [
                    'range' => [
                        'price' => [
                            'gte' => 50.0,
                            'lte' => 200.0,
                        ],
                    ],
                ],
            ],
            $capturedQuery['query']['bool']['filter']
        );
        $this->assertSame(
            [
                'price' => [
                    'order' => 'asc',
                ],
            ],
            $capturedQuery['sort']
        );
    }

    public function testSearchWithPriceRange(): void
    {
        $result = $this->searchService->search('', null, 50.0, 200.0);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total']);
    }

    public function testSearchWithAvailabilityFilter(): void
    {
        $result = $this->searchService->search('', null, null, null, true);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total']);
    }

    public function testSearchWithPagination(): void
    {
        $result = $this->searchService->search('', null, null, null, false, 2, 10);

        $this->assertEquals(2, $result['page']);
        $this->assertEquals(10, $result['perPage']);
    }

    public function testSearchWithSorting(): void
    {
        $capturedQuery = null;

        $this->index
            ->expects($this->once())
            ->method('search')
            ->with($this->callback(static function (Query $query) use (&$capturedQuery): bool {
                $capturedQuery = $query->toArray();

                return true;
            }));

        $result = $this->searchService->search('', null, null, null, false, 1, 20, 'title', 'desc');

        $this->assertIsArray($result);
        $this->assertSame(
            [
                'title.keyword' => [
                    'order' => 'desc',
                ],
            ],
            $capturedQuery['sort']
        );
    }

    public function testSearchByShop(): void
    {
        $result = $this->searchService->searchByShop(5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(0, $result['total']);
    }

    public function testSearchByShopWithQuery(): void
    {
        $result = $this->searchService->searchByShop(5, 'test product', 50.0, 200.0, 10);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total']);
    }

    public function testSuggest(): void
    {
        $suggestions = $this->searchService->suggest('test');

        $this->assertIsArray($suggestions);
        $this->assertEmpty($suggestions);
    }
}
