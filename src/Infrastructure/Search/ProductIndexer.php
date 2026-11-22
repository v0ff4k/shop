<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use Elastica\Client;
use Elastica\Document;
use Elastica\Index;

/**
 * Сервис для индексации товаров в Elasticsearch.
 */
final class ProductIndexer
{
    private const INDEX_ALIAS = 'products';
    private const INDEX_PREFIX = 'products_';

    private Index $index;

    public function __construct(
        private readonly Client $elasticaClient
    ) {
        $this->index = $this->elasticaClient->getIndex(self::INDEX_ALIAS);
    }

    /**
     * Проиндексировать товар
     */
    public function indexProduct(array $productData): void
    {
        $document = new Document(
            (string) $productData['id'],
            [
                'id' => $productData['id'],
                'title' => $productData['title'],
                'description' => $productData['description'] ?? '',
                'slug' => $productData['slug'],
                'price' => (float) $productData['price'],
                'category_id' => (int) $productData['category_id'],
                'available' => (int) ($productData['available'] ?? 0),
                'added' => $productData['added'] ?? date('Y-m-d H:i:s'),
                'viewed' => (int) ($productData['viewed'] ?? 0),
            ]
        );

        $this->index->addDocument($document);
        $this->index->refresh();
    }

    /**
     * Проиндексировать несколько товаров без частого обновления индекса
     * Для пакетной обработки чтобы снизить нагрузку.
     */
    public function indexProductsBatch(array $products): void
    {
        $documents = [];
        foreach ($products as $product) {
            $documents[] = new Document(
                (string) $product['id'],
                [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'description' => $product['description'] ?? '',
                    'slug' => $product['slug'],
                    'price' => (float) $product['price'],
                    'category_id' => (int) $product['category_id'],
                    'available' => (int) ($product['available'] ?? 0),
                    'added' => $product['added'] ?? date('Y-m-d H:i:s'),
                    'viewed' => (int) ($product['viewed'] ?? 0),
                ]
            );
        }

        $this->index->addDocuments($documents);
        $this->index->refresh();
    }

    /**
     * Удалить товар из индекса.
     */
    public function removeProduct(int $productId): void
    {
        $this->index->deleteById((string) $productId);
        $this->index->refresh();
    }

    /**
     * Обновить данные товара в индексе.
     */
    public function updateProduct(int $productId, array $productData): void
    {
        $this->removeProduct($productId);
        $this->indexProduct(array_merge(['id' => $productId], $productData));
    }

    /**
     * Переиндексировать все товары.
     */
    public function reindexAll(array $products): void
    {
        $oldIndices = $this->elasticaClient->getStatus()->getIndicesWithAlias(self::INDEX_ALIAS);
        $targetIndex = $this->elasticaClient->getIndex($this->generatePhysicalIndexName());
        $targetIndex->create();

        $documents = [];
        foreach ($products as $product) {
            $documents[] = new Document(
                (string) $product['id'],
                [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'description' => $product['description'] ?? '',
                    'slug' => $product['slug'],
                    'price' => (float) $product['price'],
                    'category_id' => (int) $product['category_id'],
                    'available' => (int) ($product['available'] ?? 0),
                    'added' => $product['added'] ?? date('Y-m-d H:i:s'),
                    'viewed' => (int) ($product['viewed'] ?? 0),
                ]
            );
        }

        $targetIndex->addDocuments($documents);
        $targetIndex->refresh();
        $targetIndex->addAlias(self::INDEX_ALIAS, true);

        foreach ($oldIndices as $oldIndex) {
            if ($oldIndex->getName() === $targetIndex->getName()) {
                continue;
            }

            if ($oldIndex->exists()) {
                $oldIndex->delete();
            }
        }
    }

    /**
     * Получить объект индекса.
     */
    public function getIndex(): Index
    {
        return $this->index;
    }

    private function generatePhysicalIndexName(): string
    {
        return self::INDEX_PREFIX.date('YmdHis').'_'.bin2hex(random_bytes(4));
    }
}
