<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\DTO\Request\ProductSearchRequest;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use App\Infrastructure\Cache\CacheInvalidationService;
use App\Infrastructure\Cache\CacheServiceInterface;
use App\Infrastructure\Search\ProductSearchService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Сервис для чтения данных о продуктах (CQRS - Query side)
 * Использует raw SQL для эффективного чтения.
 *
 * @see $connection \Doctrine\DBAL\Connection
 */
final class ProductQueryService
{
    private const CACHE_TTL_CATEGORY = 300; // 5мин
    private const CACHE_TTL_PRODUCT = 3600; // 1 час
    private const CACHE_TTL_LIST = 600; // 10 минут
    private const CACHE_KEY_PRODUCT_SEARCH = 'product:search:';
    private const CACHE_KEY_PRODUCT_SLUG = 'product:slug:';
    private const CACHE_KEY_PRODUCT_NEW = 'product:new:%d';
    private const CACHE_KEY_PRODUCT_POPULAR = 'product:popular:%d';
    private const CACHE_KEY_PRODUCT_CATEGORY = 'product:category:%d:%d:%d';

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheServiceInterface $cache,
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly ?ProductSearchService $productSearchService = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Поиск продуктов с фильтрами.
     *
     * @return array{items: array<Product>, total: int}
     */
    public function search(ProductSearchRequest $request): array
    {
        $searchContext = $this->buildSearchContext($request);

        // Генерируем ключ кэша на основе параметров запроса
        $cacheKey = self::CACHE_KEY_PRODUCT_SEARCH.md5(
            serialize(
                [
                    'query' => $request->query,
                    'categoryId' => $request->categoryId,
                    'minPrice' => $request->minPrice,
                    'maxPrice' => $request->maxPrice,
                    'sortBy' => $request->sortBy,
                    'sortOrder' => $request->sortOrder,
                    'page' => $request->page,
                    'limit' => $request->limit,
                ]
            )
        );

        // Пробуем получить из кэша
        $cached = $this->cache->get($cacheKey);

        if (\is_array($cached) && isset($cached['total'], $cached['ids'], $cached['rows'])) {
            $rowsByProductId = [];
            foreach ($cached['rows'] as $row) {
                $rowsByProductId[(int) $row['id']][] = $row;
            }

            $products = [];
            foreach ($cached['ids'] as $productId) {
                $productId = (int) $productId;
                if (isset($rowsByProductId[$productId])) {
                    $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
                }
            }

            return [
                'items' => $products,
                'total' => (int) $cached['total'],
            ];
        }

        if ($cached !== null) {
            return $cached; // backward compatibility for old cache payload
        }

        $elasticResult = $this->searchWithElasticSearch($request, $searchContext, $cacheKey);
        if ($elasticResult !== null && $elasticResult['items'] !== []) {
            return $elasticResult;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->from('product', 'p');

        // Фильтры
        if ($request->query !== null && trim($request->query) !== '') {
            $qb->andWhere('p.title ILIKE :query OR p.description ILIKE :query');
            $qb->setParameter('query', '%'.$request->query.'%');
        }

        if ($request->categoryId !== null) {
            $qb->andWhere('p.category_id = :categoryId');
            $qb->setParameter('categoryId', $request->categoryId);
        }

        if ($request->minPrice !== null) {
            $qb->andWhere('p.price >= :minPrice');
            $qb->setParameter('minPrice', $request->minPrice);
        }

        if ($request->maxPrice !== null) {
            $qb->andWhere('p.price <= :maxPrice');
            $qb->setParameter('maxPrice', $request->maxPrice);
        }

        // Получаем общее количество
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT p.id)');
        $total = (int) $countQb->executeQuery()->fetchOne();

        // Сортировка
        $sortBy = $request->getValidSortBy();
        $sortOrder = $request->getValidSortOrder();
        $qb->select('p.id')
            ->orderBy("p.$sortBy", $sortOrder);

        // Лимиты
        $qb->setFirstResult($request->getOffset())->setMaxResults($request->limit);

        $productIds = array_map('intval', $qb->executeQuery()->fetchFirstColumn());
        if ($productIds === []) {
            $result = [
                'items' => [],
                'total' => $total,
            ];

            $this->cache->setWithTag($cacheKey, ['total' => $total, 'ids' => [], 'rows' => []], CacheInvalidationService::TAG_PRODUCT_LIST, self::CACHE_TTL_LIST);

            return $result;
        }

        [$products, $rows] = $this->fetchProductsAndRowsByIds($productIds, $sortBy, $sortOrder);

        $result = [
            'items' => $products,
            'total' => $total,
        ];

        // Сохраняем в кэш с тегом (строки + id, чтобы восстановить изображения)
        $this->cache->setWithTag(
            $cacheKey,
            ['total' => $total, 'ids' => $productIds, 'rows' => $rows],
            CacheInvalidationService::TAG_PRODUCT_LIST,
            self::CACHE_TTL_LIST
        );

        return $result;
    }

    public function searchOld(ProductSearchRequest $request): array
    {
        // Генерируем ключ кэша на основе параметров запроса
        $cacheKey = self::CACHE_KEY_PRODUCT_SEARCH.md5(
            serialize(
                [
                    'query' => $request->query,
                    'categoryId' => $request->categoryId,
                    'minPrice' => $request->minPrice,
                    'maxPrice' => $request->maxPrice,
                    'sortBy' => $request->sortBy,
                    'sortOrder' => $request->sortOrder,
                    'page' => $request->page,
                    'limit' => $request->limit,
                ]
            )
        );

        // Пробуем получить из кэша
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('p.*')
            ->from('product', 'p')
            ->leftJoin('p', 'category', 'c', 'p.category_id = c.id');

        // Фильтры
        if ($request->query !== null && trim($request->query) !== '') {
            $qb->andWhere('p.title ILIKE :query OR p.description ILIKE :query');
            $qb->setParameter('query', '%'.$request->query.'%');
        }

        if ($request->categoryId !== null) {
            $qb->andWhere('p.category_id = :categoryId');
            $qb->setParameter('categoryId', $request->categoryId);
        }

        if ($request->minPrice !== null) {
            $qb->andWhere('p.price >= :minPrice');
            $qb->setParameter('minPrice', $request->minPrice);
        }

        if ($request->maxPrice !== null) {
            $qb->andWhere('p.price <= :maxPrice');
            $qb->setParameter('maxPrice', $request->maxPrice);
        }

        // Получаем общее количество
        $countQb = clone $qb;
        $countQb->select('COUNT(p.id)');
        $total = (int) $countQb->executeQuery()->fetchOne();

        // Сортировка
        $sortBy = $request->getValidSortBy();
        $sortOrder = $request->getValidSortOrder();
        $qb->orderBy("p.$sortBy", $sortOrder);

        // Лимиты
        $qb->setFirstResult($request->getOffset())->setMaxResults($request->limit);

        // Выполняем запрос и мапим на сущности
        $rows = $qb->executeQuery()->fetchAllAssociative();
        $products = array_map(fn ($row) => $this->mapToEntity($row), $rows);

        $result = [
            'items' => $products,
            'total' => $total,
        ];

        // Сохраняем в кэш с тегом
        $this->cache->setWithTag($cacheKey, $result, CacheInvalidationService::TAG_PRODUCT_LIST, self::CACHE_TTL_LIST);

        return $result;
    }

    public function findBySlug(string $slug): ?Product
    {
        $cacheKey = self::CACHE_KEY_PRODUCT_SLUG.$slug;

        // Пробуем получить из кэша
        $cachedRows = $this->cache->get($cacheKey);

        if ($cachedRows !== null) {
            // Если в кэше null (продукт не найден)
            if ($cachedRows === false || $cachedRows === null) {
                return null;
            }

            // Восстанавливаем продукт из полного набора строк
            return $this->buildProductFromRows($cachedRows);
        }

        // Загружаем продукт с изображениями
        $qb = $this->connection->createQueryBuilder();
        $qb->select('p.*', 'pi.id as image_id', 'pi.path as image_path', 'pi.alt as image_alt', 'pi.position as image_position', 'pi.uploaded_at as image_uploaded_at')
            ->from('product', 'p')
            ->leftJoin('p', 'product_image', 'pi', 'p.id = pi.product_id')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('pi.position', 'ASC');

        $rows = $qb->executeQuery()->fetchAllAssociative();

        if (empty($rows)) {
            // Кэшируем отсутствие результата
            $this->cache->set($cacheKey, null, 60);

            return null;
        }

        // Строим объект
        $product = $this->buildProductFromRows($rows);

        // Сохраняем ВЕСЬ массив строк в кэш
        $this->cache->setWithTag($cacheKey, $rows, CacheInvalidationService::TAG_PRODUCT_DETAIL, self::CACHE_TTL_PRODUCT);

        return $product;
    }

    /**
     * Найти новые продукты.
     *
     * @return array<Product>
     */
    public function findNew(int $limit = 10): array
    {
        $cacheKey = \sprintf(self::CACHE_KEY_PRODUCT_NEW, $limit);
        $cachedRows = $this->cache->get($cacheKey);

        if (\is_array($cachedRows) && isset($cachedRows['ids'], $cachedRows['rows'])) {
            $rowsByProductId = [];
            foreach ($cachedRows['rows'] as $row) {
                $rowsByProductId[(int) $row['id']][] = $row;
            }

            $products = [];
            foreach ($cachedRows['ids'] as $productId) {
                $productId = (int) $productId;
                if (isset($rowsByProductId[$productId])) {
                    $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
                }
            }

            return $products;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('p.id')
            ->from('product', 'p')
            ->orderBy('p.added', 'DESC')
            ->setMaxResults($limit);

        $productIds = array_map('intval', $qb->executeQuery()->fetchFirstColumn());
        if ($productIds === []) {
            $this->cache->setWithTag($cacheKey, ['ids' => [], 'rows' => []], CacheInvalidationService::TAG_PRODUCT_LIST, self::CACHE_TTL_LIST);

            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'p.*',
            'pi.id as image_id',
            'pi.path as image_path',
            'pi.alt as image_alt',
            'pi.position as image_position',
            'pi.uploaded_at as image_uploaded_at'
        )
            ->from('product', 'p')
            ->leftJoin('p', 'product_image', 'pi', 'p.id = pi.product_id')
            ->where('p.id IN (:productIds)')
            ->setParameter('productIds', $productIds, ArrayParameterType::INTEGER)
            ->orderBy('p.added', 'DESC')
            ->addOrderBy('pi.position', 'ASC');

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $rowsByProductId = [];
        foreach ($rows as $row) {
            $rowsByProductId[(int) $row['id']][] = $row;
        }

        $products = [];
        foreach ($productIds as $productId) {
            if (isset($rowsByProductId[$productId])) {
                $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
            }
        }

        $this->cache->setWithTag(
            $cacheKey,
            ['ids' => $productIds, 'rows' => $rows],
            CacheInvalidationService::TAG_PRODUCT_LIST,
            self::CACHE_TTL_LIST
        );

        return $products;
    }

    /**
     * Найти популярные продукты.
     *
     * @return array<Product>
     */
    public function findPopular(int $limit = 10): array
    {
        $cacheKey = \sprintf(self::CACHE_KEY_PRODUCT_POPULAR, $limit);
        $cachedRows = $this->cache->get($cacheKey);

        if (\is_array($cachedRows) && isset($cachedRows['ids'], $cachedRows['rows'])) {
            $rowsByProductId = [];
            foreach ($cachedRows['rows'] as $row) {
                $rowsByProductId[(int) $row['id']][] = $row;
            }

            $products = [];
            foreach ($cachedRows['ids'] as $productId) {
                $productId = (int) $productId;
                if (isset($rowsByProductId[$productId])) {
                    $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
                }
            }

            return $products;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('p.id')
            ->from('product', 'p')
            ->orderBy('p.viewed', 'DESC')
            ->setMaxResults($limit);

        $productIds = array_map('intval', $qb->executeQuery()->fetchFirstColumn());
        if ($productIds === []) {
            $this->cache->setWithTag($cacheKey, ['ids' => [], 'rows' => []], CacheInvalidationService::TAG_PRODUCT_LIST, self::CACHE_TTL_LIST);

            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'p.*',
            'pi.id as image_id',
            'pi.path as image_path',
            'pi.alt as image_alt',
            'pi.position as image_position',
            'pi.uploaded_at as image_uploaded_at'
        )
            ->from('product', 'p')
            ->leftJoin('p', 'product_image', 'pi', 'p.id = pi.product_id')
            ->where('p.id IN (:productIds)')
            ->setParameter('productIds', $productIds, ArrayParameterType::INTEGER)
            ->orderBy('p.viewed', 'DESC')
            ->addOrderBy('pi.position', 'ASC');

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $rowsByProductId = [];
        foreach ($rows as $row) {
            $rowsByProductId[(int) $row['id']][] = $row;
        }

        $products = [];
        foreach ($productIds as $productId) {
            if (isset($rowsByProductId[$productId])) {
                $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
            }
        }

        $this->cache->setWithTag(
            $cacheKey,
            ['ids' => $productIds, 'rows' => $rows],
            CacheInvalidationService::TAG_PRODUCT_LIST,
            self::CACHE_TTL_LIST
        );

        return $products;
    }

    /**
     * Найти продукты по категории.
     *
     * @return array<Product>
     */
    public function findByCategoryOld(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();

        $qb->select('p.*')
            ->from('product', 'p')
            ->where('p.category_id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('p.added', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn ($row) => $this->buildProductFromRows($row), $rows);
    }

    //  dd($qb->getSQL(),$qb->getParameters());
    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        $cacheKey = \sprintf(self::CACHE_KEY_PRODUCT_CATEGORY, $categoryId, $limit, $offset);

        // Пробуем получить из кэша
        $cachedRows = $this->cache->get($cacheKey);

        if (\is_array($cachedRows) && isset($cachedRows['ids'], $cachedRows['rows'])) {
            $rowsByProductId = [];
            foreach ($cachedRows['rows'] as $row) {
                $rowsByProductId[(int) $row['id']][] = $row;
            }

            $products = [];
            foreach ($cachedRows['ids'] as $productId) {
                $productId = (int) $productId;
                if (isset($rowsByProductId[$productId])) {
                    $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
                }
            }

            return $products;
        }

        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();

        $qb->select('p.id')
            ->from('product', 'p')
            ->where('p.category_id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('p.added', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $productIds = array_map('intval', $qb->executeQuery()->fetchFirstColumn());
        if ($productIds === []) {
            $this->cache->set($cacheKey, ['ids' => [], 'rows' => []], self::CACHE_TTL_CATEGORY);

            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'p.*',
            'pi.id as image_id',
            'pi.path as image_path',
            'pi.alt as image_alt',
            'pi.position as image_position',
            'pi.uploaded_at as image_uploaded_at'
        )
            ->from('product', 'p')
            ->leftJoin('p', 'product_image', 'pi', 'p.id = pi.product_id')
            ->where('p.id IN (:productIds)')
            ->setParameter('productIds', $productIds, ArrayParameterType::INTEGER)
            ->orderBy('p.added', 'DESC')
            ->addOrderBy('pi.position', 'ASC');

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $rowsByProductId = [];
        foreach ($rows as $row) {
            $rowsByProductId[(int) $row['id']][] = $row;
        }

        $products = [];
        foreach ($productIds as $productId) {
            if (isset($rowsByProductId[$productId])) {
                $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
            }
        }

        $this->cache->setWithTag(
            $cacheKey,
            ['ids' => $productIds, 'rows' => $rows],
            CacheInvalidationService::TAG_PRODUCT_LIST,
            self::CACHE_TTL_CATEGORY
        );

        return $products;
    }

    /**
     * Посчитать количество продуктов по категории.
     */
    public function countByCategory(int $categoryId): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(p.id)')
            ->from('product', 'p')
            ->where('p.category_id = :categoryId')
            ->setParameter('categoryId', $categoryId);

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * Увеличить счетчик просмотров.
     */
    public function incrementViewed(Product $product): void
    {
        $this->incrementViewedById((int) $product->getId());
    }

    /**
     * Увеличить счетчик просмотров по ID (используется async-flush из Redis).
     */
    public function incrementViewedById(int $productId, int $count = 1): void
    {
        $this->connection->executeStatement(
            'UPDATE product SET viewed = viewed + :count, last_viewed = NOW() WHERE id = :id',
            ['count' => max(1, $count), 'id' => $productId]
        );
    }

    /**
     * @param array<string, mixed> $searchContext
     *
     * @return array{items: array<Product>, total: int}|null
     */
    private function searchWithElasticSearch(ProductSearchRequest $request, array $searchContext, string $cacheKey): ?array
    {
        if ($this->productSearchService === null) {
            return null;
        }

        try {
            $esResult = $this->productSearchService->search(
                query: $request->query ?? '',
                categoryId: $request->categoryId,
                minPrice: $request->minPrice,
                maxPrice: $request->maxPrice,
                availableOnly: false,
                page: $request->page,
                perPage: $request->limit,
                sortBy: $request->getValidSortBy(),
                sortOrder: $request->getValidSortOrder()
            );
        } catch (\Throwable $e) {
            $this->logger?->error('Elasticsearch search failed, fallback to SQL', $searchContext + [
                'error' => $e->getMessage(),
                'exception' => $e,
                'exception_class' => $e::class,
            ]);

            return null;
        }

        $productIds = $this->extractProductIds($esResult['results'] ?? []);

        if ($productIds === []) {
            $result = [
                'items' => [],
                'total' => (int) ($esResult['total'] ?? 0),
            ];

            $this->cache->setWithTag(
                $cacheKey,
                ['total' => $result['total'], 'ids' => [], 'rows' => []],
                CacheInvalidationService::TAG_PRODUCT_LIST,
                self::CACHE_TTL_LIST
            );

            return $result;
        }

        [$products, $rows] = $this->fetchProductsAndRowsByIds(
            $productIds,
            $request->getValidSortBy(),
            $request->getValidSortOrder()
        );

        $result = [
            'items' => $products,
            'total' => (int) ($esResult['total'] ?? \count($products)),
        ];

        $this->cache->setWithTag(
            $cacheKey,
            ['total' => $result['total'], 'ids' => $productIds, 'rows' => $rows],
            CacheInvalidationService::TAG_PRODUCT_LIST,
            self::CACHE_TTL_LIST
        );

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     *
     * @return array<int>
     */
    private function extractProductIds(array $results): array
    {
        return array_values(
            array_filter(
                array_map(
                    static function (array $item): int {
                        $id = $item['id'] ?? $item['_id'] ?? 0;

                        return (int) $id;
                    },
                    $results
                ),
                static fn (int $id): bool => $id > 0
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSearchContext(ProductSearchRequest $request): array
    {
        return [
            'query' => $request->query,
            'category_id' => $request->categoryId,
            'min_price' => $request->minPrice,
            'max_price' => $request->maxPrice,
            'page' => $request->page,
            'limit' => $request->limit,
            'sort_by' => $request->getValidSortBy(),
            'sort_order' => $request->getValidSortOrder(),
        ];
    }

    /**
     * @param array<int> $productIds
     *
     * @return array{0: array<Product>, 1: array<int, array<string, mixed>>}
     */
    private function fetchProductsAndRowsByIds(array $productIds, string $sortBy, string $sortOrder): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'p.*',
            'pi.id as image_id',
            'pi.path as image_path',
            'pi.alt as image_alt',
            'pi.position as image_position',
            'pi.uploaded_at as image_uploaded_at'
        )
            ->from('product', 'p')
            ->leftJoin('p', 'product_image', 'pi', 'p.id = pi.product_id')
            ->where('p.id IN (:productIds)')
            ->setParameter('productIds', $productIds, ArrayParameterType::INTEGER)
            ->orderBy("p.$sortBy", $sortOrder)
            ->addOrderBy('pi.position', 'ASC');

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $rowsByProductId = [];
        foreach ($rows as $row) {
            $rowsByProductId[(int) $row['id']][] = $row;
        }

        $products = [];
        foreach ($productIds as $productId) {
            if (isset($rowsByProductId[$productId])) {
                $products[] = $this->buildProductFromRows($rowsByProductId[$productId]);
            }
        }

        return [$products, $rows];
    }

    /**
     * Маппинг строки БД в сущность Product.
     */
    private function mapToEntity(array $row): Product
    {
        $product = new \ReflectionClass(Product::class);
        $instance = $product->newInstanceWithoutConstructor();

        // Инициализируем Collection поля
        $imagesProp = $product->getProperty('images');
        $imagesProp->setAccessible(true);
        $imagesProp->setValue($instance, new ArrayCollection());

        // Поля, которые требуют конвертации в DateTimeImmutable
        $dateTimeFields = ['added', 'lastViewed', 'edited'];

        foreach ($row as $property => $value) {
            $propertyName = $this->snakeToCamel($property);

            if ($product->hasProperty($propertyName)) {
                $prop = $product->getProperty($propertyName);
                $prop->setAccessible(true);

                // Конвертируем строку в DateTimeImmutable
                if (\in_array($propertyName, $dateTimeFields, true) && $value !== null && \is_string($value)) {
                    $value = new \DateTimeImmutable($value);
                }

                $prop->setValue($instance, $value);
            }
        }

        return $instance;
    }

    private function mapToEntityOld(array $row): Product
    {
        $product = new \ReflectionClass(Product::class);
        $instance = $product->newInstanceWithoutConstructor();

        // Инициализируем Collection поля
        $imagesProp = $product->getProperty('images');
        $imagesProp->setAccessible(true);
        $imagesProp->setValue($instance, new ArrayCollection());

        // Поля, которые требуют конвертации в DateTimeImmutable
        $dateTimeFields = ['added', 'lastViewed', 'edited'];

        foreach ($row as $property => $value) {
            $propertyName = $this->snakeToCamel($property);

            if ($product->hasProperty($propertyName)) {
                $prop = $product->getProperty($propertyName);
                $prop->setAccessible(true);

                // Конвертируем строку в DateTimeImmutable
                if (\in_array($propertyName, $dateTimeFields, true) && $value !== null && \is_string($value)) {
                    $value = new \DateTimeImmutable($value);
                }

                $prop->setValue($instance, $value);
            }
        }

        return $instance;
    }

    private function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    private function buildProductFromRows(array $rows): Product
    {
        if (empty($rows)) {
            throw new \InvalidArgumentException('Cannot build product from empty dataset');
        }

        $firsRow = isset($rows['id']) ? $rows : $rows[0];

        // Маппим базовый продукт из первой строки
        $product = $this->mapToEntity($firsRow);

        // Собираем изображения из всех строк
        $images = [];
        foreach ($rows as $row) {
            if (!empty($row['image_id'])) {
                $images[] = $this->mapToProductImage($row);
            }
        }

        // Устанавливаем изображения
        $reflection = new \ReflectionClass(Product::class);
        $imagesProp = $reflection->getProperty('images');
        $imagesProp->setAccessible(true);
        $imagesProp->setValue($product, new ArrayCollection($images));

        return $product;
    }

    /**
     * Маппинг строки БД в сущность ProductImage.
     */
    private function mapToProductImage(array $row): ProductImage
    {
        $image = new ProductImage($row['image_path']);

        $imageReflection = new \ReflectionClass(ProductImage::class);

        $idProp = $imageReflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($image, (int) $row['image_id']);

        if ($row['image_alt'] !== null) {
            $altProp = $imageReflection->getProperty('alt');
            $altProp->setAccessible(true);
            $altProp->setValue($image, $row['image_alt']);
        }

        $positionProp = $imageReflection->getProperty('position');
        $positionProp->setAccessible(true);
        $positionProp->setValue($image, (int) ($row['image_position'] ?? 0));

        if ($row['image_uploaded_at'] !== null) {
            $uploadedAtProp = $imageReflection->getProperty('uploadedAt');
            $uploadedAtProp->setAccessible(true);
            $uploadedAtProp->setValue($image, new \DateTimeImmutable($row['image_uploaded_at']));
        }

        return $image;
    }
}
