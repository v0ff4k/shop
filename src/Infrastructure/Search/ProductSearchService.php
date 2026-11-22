<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use Elastica\Client;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\MultiMatch;
use Elastica\Query\Range;
use Elastica\Query\Term;
use Psr\Log\LoggerInterface;

/**
 * Сервис для поиска товаров через Elasticsearch.
 */
final class ProductSearchService
{
    public function __construct(
        private readonly Client $elasticaClient,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Поиск товаров по запросу.
     *
     * @return array{results: array, total: int, page: int, perPage: int, pages: int}
     */
    public function search(
        string $query,
        ?int $categoryId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?bool $availableOnly = false,
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'relevance',
        string $sortOrder = 'desc'
    ): array {
        $boolQuery = new BoolQuery();

        // Полнотекстовый поиск по названию и описанию
        if (!empty($query)) {
            $multiMatchQuery = new MultiMatch();
            $multiMatchQuery->setQuery($query);
            $multiMatchQuery->setFields(['title^2', 'description']);
            $multiMatchQuery->setFuzziness(0.5);
            $multiMatchQuery->setType(MultiMatch::TYPE_BEST_FIELDS);

            $boolQuery->addMust($multiMatchQuery);
        }

        // Фильтр по категории
        if ($categoryId !== null) {
            $categoryFilter = new Term();
            $categoryFilter->setTerm('category_id', $categoryId);
            $boolQuery->addFilter($categoryFilter);
        }

        // Фильтр по цене
        if ($minPrice !== null || $maxPrice !== null) {
            $rangeArgs = [];
            if ($minPrice !== null) {
                $rangeArgs['gte'] = $minPrice;
            }
            if ($maxPrice !== null) {
                $rangeArgs['lte'] = $maxPrice;
            }

            $rangeFilter = new Range('price', $rangeArgs);
            $boolQuery->addFilter($rangeFilter);
        }

        // Фильтр по доступности
        if ($availableOnly) {
            $availableFilter = new Range();
            $availableFilter->addField('available', ['gt' => 0]);
            $boolQuery->addFilter($availableFilter);
        }

        // Создаём запрос
        $elasticaQuery = new Query();
        $elasticaQuery->setQuery($boolQuery);

        // Пагинация
        $elasticaQuery->setFrom(($page - 1) * $perPage);
        $elasticaQuery->setSize($perPage);

        // Сортировка
        $elasticaQuery->setSort($this->getSortParams($sortBy, $sortOrder));

        // Выполняем поиск
        $index = $this->elasticaClient->getIndex('products');

        try {
            $resultSet = $index->search($elasticaQuery);
        } catch (\Throwable $e) {
            $this->logger?->error('Elasticsearch search execution failed', [
                'query' => $query,
                'category_id' => $categoryId,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'available_only' => $availableOnly,
                'page' => $page,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'error' => $e->getMessage(),
                'exception' => $e,
                'exception_class' => $e::class,
            ]);

            throw $e;
        }

        $totalResults = $resultSet->getTotalHits();
        $results = [];

        foreach ($resultSet->getResults() as $result) {
            $source = $result->getSource();
            if (!isset($source['id']) || (int) $source['id'] <= 0) {
                $source['id'] = (int) $result->getId();
            }

            $results[] = $source;
        }

        return [
            'results' => $results,
            'total' => $totalResults,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => (int) ceil($totalResults / $perPage),
        ];
    }

    /**
     * Поиск товаров в конкретном магазине (по категории).
     *
     * @return array{results: array, total: int}
     */
    public function searchByShop(
        int $categoryId,
        string $query = '',
        ?float $minPrice = null,
        ?float $maxPrice = null,
        int $limit = 50
    ): array {
        $boolQuery = new BoolQuery();

        // Обязательный фильтр по категории
        $categoryFilter = new Term();
        $categoryFilter->setTerm('category_id', $categoryId);
        $boolQuery->addFilter($categoryFilter);

        // Поиск по запросу
        if (!empty($query)) {
            $matchQuery = new MatchQuery();
            $matchQuery->setFieldQuery('title', $query);
            $matchQuery->setFieldFuzziness('title', 0.5);
            $boolQuery->addMust($matchQuery);
        }

        // Фильтр по цене
        if ($minPrice !== null || $maxPrice !== null) {
            $rangeArgs = [];
            if ($minPrice !== null) {
                $rangeArgs['gte'] = $minPrice;
            }
            if ($maxPrice !== null) {
                $rangeArgs['lte'] = $maxPrice;
            }

            $rangeFilter = new Range('price', $rangeArgs);
            $boolQuery->addFilter($rangeFilter);
        }

        $elasticaQuery = new Query();
        $elasticaQuery->setQuery($boolQuery);
        $elasticaQuery->setSize($limit);

        $index = $this->elasticaClient->getIndex('products');

        try {
            $resultSet = $index->search($elasticaQuery);
        } catch (\Throwable $e) {
            $this->logger?->error('Elasticsearch searchByShop execution failed', [
                'query' => $query,
                'category_id' => $categoryId,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'limit' => $limit,
                'error' => $e->getMessage(),
                'exception' => $e,
                'exception_class' => $e::class,
            ]);

            throw $e;
        }

        $results = [];
        foreach ($resultSet->getResults() as $result) {
            $source = $result->getSource();
            if (!isset($source['id']) || (int) $source['id'] <= 0) {
                $source['id'] = (int) $result->getId();
            }

            $results[] = $source;
        }

        return [
            'results' => $results,
            'total' => $resultSet->getTotalHits(),
        ];
    }

    /**
     * Автодополнение (suggest) для поискового запроса.
     *
     * @return array<string>
     */
    public function suggest(string $query, int $limit = 10): array
    {
        $matchQuery = new MatchQuery();
        $matchQuery->setFieldQuery('title', $query);
        $matchQuery->setFieldOperator('title', 'and');

        $elasticaQuery = new Query();
        $elasticaQuery->setQuery($matchQuery);
        $elasticaQuery->setSize($limit);
        $elasticaQuery->setSource(['title']);

        $index = $this->elasticaClient->getIndex('products');

        try {
            $resultSet = $index->search($elasticaQuery);
        } catch (\Throwable $e) {
            $this->logger?->error('Elasticsearch suggest execution failed', [
                'query' => $query,
                'limit' => $limit,
                'error' => $e->getMessage(),
                'exception' => $e,
                'exception_class' => $e::class,
            ]);

            throw $e;
        }

        $suggestions = [];
        foreach ($resultSet->getResults() as $result) {
            $source = $result->getSource();
            $suggestions[] = $source['title'];
        }

        return array_unique($suggestions);
    }

    /**
     * Параметры сортировки.
     */
    private function getSortParams(string $sortBy, string $sortOrder): array
    {
        $order = strtolower($sortOrder);

        return match ($sortBy) {
            'price' => ['price' => ['order' => $order]],
            'date' => ['added' => ['order' => $order]],
            'views' => ['viewed' => ['order' => $order]],
            'title' => ['title.keyword' => ['order' => $order]],
            default => ['_score' => ['order' => 'desc']], // relevance
        };
    }
}
