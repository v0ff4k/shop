<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Search;

use App\Infrastructure\Search\ProductIndexer;
use Elastica\Client;
use Elastica\Index;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductIndexerTest extends TestCase
{
    private Client&MockObject $elasticaClient;
    private Index&MockObject $aliasIndex;
    private Index&MockObject $reindexIndex;
    private Index&MockObject $oldIndex;
    private \Elastica\Status&MockObject $status;
    private ?string $generatedIndexName = null;
    private ProductIndexer $productIndexer;

    protected function setUp(): void
    {
        $this->elasticaClient = $this->createMock(Client::class);
        $this->aliasIndex = $this->createMock(Index::class);
        $this->reindexIndex = $this->createMock(Index::class);
        $this->oldIndex = $this->createMock(Index::class);
        $this->status = $this->createMock(\Elastica\Status::class);

        $this->elasticaClient
            ->method('getIndex')
            ->willReturnCallback(function (string $name): Index {
                if ($name === 'products') {
                    return $this->aliasIndex;
                }

                $this->generatedIndexName = $name;

                return $this->reindexIndex;
            });

        $this->elasticaClient
            ->method('getStatus')
            ->willReturn($this->status);

        $this->productIndexer = new ProductIndexer($this->elasticaClient);
    }

    public function testIndexProduct(): void
    {
        $productData = [
            'id' => 1,
            'title' => 'Test Product',
            'description' => 'Test Description',
            'slug' => 'test-product',
            'price' => 99.99,
            'category_id' => 5,
            'available' => 10,
            'added' => '2026-04-05 10:00:00',
            'viewed' => 0,
        ];

        $this->aliasIndex
            ->expects($this->once())
            ->method('addDocument')
            ->with($this->callback(static function ($document) {
                $source = $document->getData();

                return $source['title'] === 'Test Product'
                    && $source['price'] === 99.99
                    && $source['slug'] === 'test-product';
            }));

        $this->aliasIndex
            ->expects($this->once())
            ->method('refresh');

        $this->productIndexer->indexProduct($productData);
    }

    public function testRemoveProduct(): void
    {
        $this->aliasIndex
            ->expects($this->once())
            ->method('deleteById')
            ->with('42');

        $this->aliasIndex
            ->expects($this->once())
            ->method('refresh');

        $this->productIndexer->removeProduct(42);
    }

    public function testUpdateProduct(): void
    {
        $productId = 1;
        $productData = [
            'title' => 'Updated Product',
            'description' => 'Updated Description',
            'price' => 149.99,
            'slug' => 'updated-product',
            'category_id' => 5,
            'available' => 15,
            'viewed' => 7,
        ];

        $this->aliasIndex
            ->expects($this->once())
            ->method('deleteById')
            ->with('1');

        $this->aliasIndex
            ->expects($this->once())
            ->method('addDocument')
            ->with($this->callback(static function ($document) use ($productId) {
                $source = $document->getData();

                return $document->getId() === (string) $productId
                    && $source['title'] === 'Updated Product'
                    && $source['description'] === 'Updated Description'
                    && $source['price'] === 149.99
                    && $source['slug'] === 'updated-product'
                    && $source['category_id'] === 5
                    && $source['available'] === 15
                    && $source['viewed'] === 7;
            }));

        $this->aliasIndex
            ->expects($this->exactly(2))
            ->method('refresh');

        $this->productIndexer->updateProduct($productId, $productData);
    }

    public function testReindexAll(): void
    {
        $products = [
            [
                'id' => 1,
                'title' => 'Product 1',
                'slug' => 'product-1',
                'price' => 99.99,
                'category_id' => 1,
                'available' => 10,
                'added' => '2026-04-05 10:00:00',
                'viewed' => 5,
            ],
            [
                'id' => 2,
                'title' => 'Product 2',
                'slug' => 'product-2',
                'price' => 149.99,
                'category_id' => 2,
                'available' => 20,
                'added' => '2026-04-05 11:00:00',
                'viewed' => 10,
            ],
        ];

        $this->status
            ->expects($this->once())
            ->method('getIndicesWithAlias')
            ->with('products')
            ->willReturn([$this->oldIndex]);

        $this->reindexIndex
            ->expects($this->once())
            ->method('create');

        $this->reindexIndex
            ->expects($this->once())
            ->method('addDocuments')
            ->with($this->callback(static function ($documents) {
                return \count($documents) === 2;
            }));

        $this->reindexIndex
            ->expects($this->once())
            ->method('refresh');

        $this->reindexIndex
            ->expects($this->once())
            ->method('addAlias')
            ->with('products', true);

        $this->reindexIndex
            ->method('getName')
            ->willReturnCallback(function (): string {
                return $this->generatedIndexName ?? 'products_tmp';
            });

        $this->oldIndex
            ->expects($this->once())
            ->method('getName')
            ->willReturn('products_20260101000000_old');

        $this->oldIndex
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->oldIndex
            ->expects($this->once())
            ->method('delete');

        $this->productIndexer->reindexAll($products);
    }
}
