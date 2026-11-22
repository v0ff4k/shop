<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\DTO\Request\ProductSearchRequest;
use App\Application\Query\ProductQueryService;
use App\Domain\Shop\Entity\Product;
use App\Infrastructure\Cache\CacheInvalidationService;
use App\Infrastructure\Cache\CacheServiceInterface;
use App\Infrastructure\Search\ProductSearchService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Elastica\Client;
use Elastica\Index;
use Elastica\Result as ElasticResult;
use Elastica\ResultSet;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Тесты для ProductQueryService.
 *
 * @covers \App\Application\Query\ProductQueryService
 */
final class ProductQueryServiceTest extends TestCase
{
    private Connection|MockObject $connection;
    private CacheServiceInterface|MockObject $cache;
    private CacheInvalidationService $cacheInvalidation;
    private LoggerInterface|MockObject $cacheLogger;
    private ProductQueryService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(CacheServiceInterface::class);
        $this->cacheLogger = $this->createMock(LoggerInterface::class);
        $this->cacheInvalidation = new CacheInvalidationService(
            $this->cache,
            $this->cacheLogger
        );

        $this->service = new ProductQueryService(
            $this->connection,
            $this->cache,
            $this->cacheInvalidation
        );
    }

    public function testFindBySlugReturnsProduct(): void
    {
        // Arrange
        $slug = 'test-product';
        $rowData = [
            'id' => 1,
            'title' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'description' => 'Test description',
            'available' => 10,
            'category_id' => 1,
            'added' => '2024-01-01 00:00:00',
            'viewed' => 0,
            'last_viewed' => null,
        ];

        $this->cache
            ->method('get')
            ->willReturn(null);

        $resultMock = $this->createMock(Result::class);
        $resultMock
            ->method('fetchAllAssociative')
            ->willReturn([$rowData]);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturn($resultMock);

        // Act
        $product = $this->service->findBySlug($slug);

        // Assert
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('test-product', $product->getSlug());
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        // Arrange
        $slug = 'non-existent';

        $this->cache
            ->method('get')
            ->willReturn(null);

        $resultMock = $this->createMock(Result::class);
        $resultMock
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturn($resultMock);

        // Act
        $product = $this->service->findBySlug($slug);

        // Assert
        $this->assertNull($product);
    }

    public function testFindBySlugReturnsCachedResult(): void
    {
        // Arrange
        $slug = 'cached-product';
        $cachedData = [
            'id' => 1,
            'title' => 'Cached Product',
            'slug' => 'cached-product',
            'price' => 50.00,
            'description' => 'Cached description',
            'available' => 5,
            'category_id' => 1,
            'added' => '2024-01-01 00:00:00',
            'viewed' => 10,
            'last_viewed' => '2024-01-02 00:00:00',
        ];

        $this->cache
            ->method('get')
            ->with('product:slug:'.$slug)
            ->willReturn($cachedData);

        // Act
        $product = $this->service->findBySlug($slug);

        // Assert
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Cached Product', $product->getTitle());
    }

    public function testFindNewReturnsLimitedProducts(): void
    {
        // Arrange
        $limit = 5;
        $rows = [
            ['id' => 1, 'title' => 'Product 1', 'slug' => 'product-1', 'price' => 10.00, 'available' => 10],
            ['id' => 2, 'title' => 'Product 2', 'slug' => 'product-2', 'price' => 20.00, 'available' => 5],
        ];

        $idsResult = $this->createMock(Result::class);
        $idsResult
            ->method('fetchFirstColumn')
            ->willReturn([1, 2]);

        $rowsResult = $this->createMock(Result::class);
        $rowsResult
            ->method('fetchAllAssociative')
            ->willReturn($rows);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($idsResult, $rowsResult);

        // Act
        $products = $this->service->findNew($limit);

        // Assert
        $this->assertCount(2, $products);
        $this->assertContainsOnlyInstancesOf(Product::class, $products);
    }

    public function testFindPopularReturnsPopularProducts(): void
    {
        // Arrange
        $rows = [
            ['id' => 1, 'title' => 'Popular 1', 'slug' => 'popular-1', 'price' => 30.00, 'viewed' => 100],
            ['id' => 2, 'title' => 'Popular 2', 'slug' => 'popular-2', 'price' => 40.00, 'viewed' => 50],
        ];

        $idsResult = $this->createMock(Result::class);
        $idsResult
            ->method('fetchFirstColumn')
            ->willReturn([1, 2]);

        $rowsResult = $this->createMock(Result::class);
        $rowsResult
            ->method('fetchAllAssociative')
            ->willReturn($rows);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($idsResult, $rowsResult);

        // Act
        $products = $this->service->findPopular(10);

        // Assert
        $this->assertCount(2, $products);
        $this->assertContainsOnlyInstancesOf(Product::class, $products);
    }

    public function testCountByCategoryReturnsCorrectCount(): void
    {
        // Arrange
        $categoryId = 1;

        $resultMock = $this->createMock(Result::class);
        $resultMock
            ->method('fetchOne')
            ->willReturn(42);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturn($resultMock);

        // Act
        $count = $this->service->countByCategory($categoryId);

        // Assert
        $this->assertEquals(42, $count);
    }

    public function testSearchReturnsProductsAndTotal(): void
    {
        // Arrange
        $request = new ProductSearchRequest(
            query: 'test',
            page: 1,
            limit: 10
        );

        $rows = [
            ['id' => 1, 'title' => 'Test Product', 'slug' => 'test-product', 'price' => 99.99, 'available' => 10],
        ];

        $idsResult = $this->createMock(Result::class);
        $idsResult
            ->method('fetchFirstColumn')
            ->willReturn([1]);

        $rowsResult = $this->createMock(Result::class);
        $rowsResult
            ->method('fetchAllAssociative')
            ->willReturn($rows);

        $countResult = $this->createMock(Result::class);
        $countResult
            ->method('fetchOne')
            ->willReturn(1);

        $this->cache
            ->method('get')
            ->willReturn(null);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($countResult, $idsResult, $rowsResult);

        // Act
        $result = $this->service->search($request);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['items']);
    }

    public function testSearchUsesElasticsearchResultsWhenAvailable(): void
    {
        $request = new ProductSearchRequest(
            query: 'plastic',
            page: 1,
            limit: 10
        );

        $rowsResult = $this->createMock(Result::class);
        $rowsResult
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 15,
                    'title' => 'plastic e-gold',
                    'slug' => 'plastic-e-gold',
                    'price' => 2.15,
                    'available' => 0,
                    'category_id' => 4,
                ],
            ]);

        $this->cache
            ->method('get')
            ->willReturn(null);

        $this->connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($rowsResult);

        $searchService = $this->createElasticSearchService([
            [
                'id' => 15,
                'title' => 'plastic e-gold',
                'slug' => 'plastic-e-gold',
                'price' => 2.15,
                'available' => 0,
                'category_id' => 4,
            ],
        ], 1);

        $service = new ProductQueryService(
            $this->connection,
            $this->cache,
            $this->cacheInvalidation,
            $searchService,
            $this->cacheLogger
        );

        $result = $service->search($request);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
    }

    public function testSearchFallsBackToSqlWhenElasticsearchReturnsNoResults(): void
    {
        $request = new ProductSearchRequest(
            query: 'plastic',
            page: 1,
            limit: 10
        );

        $service = new ProductQueryService(
            $this->connection,
            $this->cache,
            $this->cacheInvalidation,
            $this->createElasticSearchService([], 0),
            $this->cacheLogger
        );

        $this->cache
            ->method('get')
            ->willReturn(null);

        $countResult = $this->createMock(Result::class);
        $countResult->method('fetchOne')->willReturn('0');
        $idsResult = $this->createMock(Result::class);
        $idsResult->method('fetchFirstColumn')->willReturn([]);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($countResult, $idsResult);

        $result = $service->search($request);

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['items']);
    }

    public function testSearchUsesElasticsearchForCatalogFiltersWithoutQuery(): void
    {
        $request = new ProductSearchRequest(
            query: null,
            categoryId: 4,
            minPrice: 10.0,
            maxPrice: 20.0,
            page: 2,
            limit: 5,
            sortBy: 'price',
            sortOrder: 'asc'
        );

        $rowsResult = $this->createMock(Result::class);
        $rowsResult
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 15,
                    'title' => 'plastic e-gold',
                    'slug' => 'plastic-e-gold',
                    'price' => 2.15,
                    'available' => 0,
                    'category_id' => 4,
                ],
            ]);

        $this->cache
            ->method('get')
            ->willReturn(null);

        $this->connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($rowsResult);

        $searchService = $this->createElasticSearchService([
            [
                'id' => 15,
                'title' => 'plastic e-gold',
                'slug' => 'plastic-e-gold',
                'price' => 2.15,
                'available' => 0,
                'category_id' => 4,
            ],
        ], 1);

        $service = new ProductQueryService(
            $this->connection,
            $this->cache,
            $this->cacheInvalidation,
            $searchService,
            $this->cacheLogger
        );

        $result = $service->search($request);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
    }

    public function testSearchFallsBackToSqlWhenElasticsearchFails(): void
    {
        $request = new ProductSearchRequest(
            query: 'plastic',
            page: 1,
            limit: 10
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Elasticsearch search failed, fallback to SQL',
                $this->callback(static function (array $context): bool {
                    return ($context['query'] ?? null) === 'plastic'
                        && ($context['error'] ?? null) === 'Elasticsearch unavailable'
                        && isset($context['exception']);
                })
            );

        $service = new ProductQueryService(
            $this->connection,
            $this->cache,
            $this->cacheInvalidation,
            $this->createElasticSearchService(exception: new \RuntimeException('Elasticsearch unavailable')),
            $logger
        );

        $this->cache
            ->method('get')
            ->willReturn(null);

        $countResult = $this->createMock(Result::class);
        $countResult
            ->method('fetchOne')
            ->willReturn(1);

        $idsResult = $this->createMock(Result::class);
        $idsResult
            ->method('fetchFirstColumn')
            ->willReturn([1]);

        $rowsResult = $this->createMock(Result::class);
        $rowsResult
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 1,
                    'title' => 'Test Product',
                    'slug' => 'test-product',
                    'price' => 99.99,
                    'available' => 10,
                    'category_id' => 1,
                ],
            ]);

        $this->connection
            ->method('createQueryBuilder')
            ->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));

        $this->connection
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($countResult, $idsResult, $rowsResult);

        $result = $service->search($request);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
    }

    public function testIncrementViewedExecutesUpdateQuery(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product
            ->method('getId')
            ->willReturn(1);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE product SET viewed = viewed + :count, last_viewed = NOW() WHERE id = :id',
                ['count' => 1, 'id' => 1]
            );

        // Act
        $this->service->incrementViewed($product);

        // Assert - выполняется один раз executeStatement
        $this->assertTrue(true);
    }

    public function testIncrementViewedByIdSupportsBatchCount(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE product SET viewed = viewed + :count, last_viewed = NOW() WHERE id = :id',
                ['count' => 5, 'id' => 10]
            );

        $this->service->incrementViewedById(10, 5);

        $this->assertTrue(true);
    }

    private function createElasticSearchService(array $results = [], ?int $totalHits = null, ?\Throwable $exception = null): ProductSearchService
    {
        $client = $this->createMock(Client::class);
        $index = $this->createMock(Index::class);
        $resultSet = $this->createMock(ResultSet::class);

        $client
            ->method('getIndex')
            ->with('products')
            ->willReturn($index);

        if ($exception !== null) {
            $index
                ->method('search')
                ->willThrowException($exception);

            return new ProductSearchService($client);
        }

        $searchResults = [];
        foreach ($results as $row) {
            $result = $this->createMock(ElasticResult::class);
            $result
                ->method('getSource')
                ->willReturn($row);
            $searchResults[] = $result;
        }

        $resultSet
            ->method('getTotalHits')
            ->willReturn($totalHits ?? \count($results));
        $resultSet
            ->method('getResults')
            ->willReturn($searchResults);

        $index
            ->method('search')
            ->willReturn($resultSet);

        return new ProductSearchService($client);
    }
}
