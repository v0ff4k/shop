<?php

declare(strict_types=1);

namespace App\Infrastructure\ImportExport;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Сервис для импорта/экспорта товаров в XML.
 *
 * Импорт работает потоково:
 *  - XMLReader читает по одному товару, документ не загружается в DOM;
 *  - товары обрабатываются батчами с flush()/clear(), поэтому память
 *    не растёт пропорционально размеру файла.
 */
final class XmlImportExportService
{
    public const DEFAULT_BATCH_SIZE = 100;

    private readonly Serializer $serializer;

    /** @var array<string, Category> */
    private array $categoryCache = [];

    private int $lastExportCount = 0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly LoggerInterface $logger,
        private readonly XmlProductReader $productReader,
        private readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        if ($this->batchSize < 1) {
            throw new \InvalidArgumentException('XML import batch size must be greater than zero');
        }

        $this->serializer = new Serializer(
            [new ObjectNormalizer()],
            [new XmlEncoder()]
        );
    }

    /**
     * Экспорт продуктов в XML.
     *
     * @param array<int> $productIds Список ID продуктов для экспорта
     */
    public function exportProducts(array $productIds = [], int $limit = 1000): string
    {
        $this->logger->info('Exporting products to XML', ['count' => \count($productIds)]);

        $products = \count($productIds) > 0
            ? array_map(
                fn ($id) => $this->productRepository->findById(ProductId::fromInt($id)),
                $productIds
            )
            : $this->productRepository->findAll($limit);

        $data = [
            'product' => [],
        ];
        $this->lastExportCount = 0;

        foreach ($products as $product) {
            if ($product === null) {
                continue;
            }

            ++$this->lastExportCount;
            $category = $product->getCategory();
            $images = array_values(array_map(
                static fn (ProductImage $image): string => $image->getPath(),
                $product->getImages()
            ));

            $item = [
                'id' => $product->getId(),
                'title' => $product->getTitle(),
                'slug' => $product->getSlug(),
                'price' => (float) $product->getPrice(),
                'description' => $product->getDescription(),
                'available' => $product->getAvailable(),
                'added' => $product->getAdded()->format('Y-m-d H:i:s'),
                'viewed' => $product->getViewed(),
            ];

            if ($images !== []) {
                // Формат совместим с XmlProductReader: <images><image>...</image></images>
                $item['images'] = ['image' => $images];
            }

            if ($category !== null) {
                $item['category'] = [
                    'slug' => $category->getSlug(),
                    'title' => $category->getTitle(),
                ];
            }

            $data['product'][] = $item;
        }

        $xml = $this->serializer->serialize(
            $data,
            'xml',
            [
                'xml_format_output' => true,
                'xml_root_node_name' => 'products',
            ]
        );

        $this->logger->info('Products exported', ['products_count' => $this->lastExportCount]);

        return $xml;
    }

    /**
     * Количество продуктов, попавших в последний экспорт.
     */
    public function getLastExportCount(): int
    {
        return $this->lastExportCount;
    }

    /**
     * Импорт продуктов из XML-строки.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function importProducts(string $xmlContent): array
    {
        $this->logger->info('Importing products from XML string', [
            'content_length' => \strlen($xmlContent),
            'batch_size' => $this->batchSize,
        ]);

        return $this->importProductsFromGenerator($this->productReader->readFromString($xmlContent));
    }

    /**
     * Импорт продуктов из XML-файла.
     *
     * Файл читается XMLReader-ом потоково: содержимое не загружается
     * в память целиком, что позволяет обрабатывать большие XML.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function importProductsFromFile(string $filePath): array
    {
        $this->logger->info('Importing products from XML file', [
            'file_path' => $filePath,
            'file_size' => is_file($filePath) ? filesize($filePath) : null,
            'batch_size' => $this->batchSize,
        ]);

        return $this->importProductsFromGenerator($this->productReader->readFromFile($filePath));
    }

    /**
     * Сохранить XML в файл.
     */
    public function saveToFile(string $xml, string $filename): string
    {
        $exportsDir = \dirname(__DIR__, 3).'/var/exports';

        if (!is_dir($exportsDir)) {
            if (!mkdir($exportsDir, 0755, true) && !is_dir($exportsDir)) {
                $err = \sprintf('Directory "%s" was not created', $exportsDir);
                $this->logger->warning($err, ['path' => $exportsDir]);

                throw new \RuntimeException($err);
            }
        }

        $filePath = $exportsDir.'/'.$filename;

        if (file_put_contents($filePath, $xml) === false) {
            throw new \RuntimeException('Failed to save XML file');
        }

        $this->logger->info('XML saved to file', ['path' => $filePath]);

        return $filePath;
    }

    /**
     * @param iterable<int, array<string, mixed>> $productsData
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function importProductsFromGenerator(iterable $productsData): array
    {
        $stats = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $this->categoryCache = [];
        $pending = 0;

        foreach ($productsData as $productData) {
            try {
                $this->importProduct($productData);
                ++$stats['imported'];
                ++$pending;
            } catch (\Throwable $e) {
                $this->logger->error(
                    'Failed to import product',
                    [
                        'error' => $e->getMessage(),
                        'slug' => $productData['slug'] ?? null,
                    ]
                );
                ++$stats['errors'];
            }

            if ($pending >= $this->batchSize) {
                $this->flushAndClear();
                $pending = 0;

                $this->logger->info('XML import batch processed', [
                    'imported' => $stats['imported'],
                    'errors' => $stats['errors'],
                ]);
            }
        }

        if ($pending > 0) {
            $this->flushAndClear();
        }

        $this->logger->info('Products imported', $stats);

        return $stats;
    }

    /**
     * Импорт одного продукта.
     */
    private function importProduct(array $data): void
    {
        if (!isset($data['slug'], $data['title'])) {
            throw new \InvalidArgumentException('Invalid product XML item');
        }

        if (isset($data['price']) && $data['price'] !== '' && !is_numeric($data['price'])) {
            throw new \InvalidArgumentException('Invalid product price');
        }

        // Проверяем существование продукта по slug
        $existingProduct = $this->productRepository->findBySlug($data['slug']);

        if ($existingProduct !== null) {
            // Обновляем существующий
            $this->updateProductFromData($existingProduct, $data);

            return;
        }

        // Создаем новый продукт
        $category = $this->findOrCreateCategory($this->extractCategory($data));

        $product = new Product(
            $data['title'],
            (string) ($data['price'] ?? '0.00'),
            $data['description'] ?? '',
            $category
        );

        $product->setAvailable((int) ($data['available'] ?? 0));
        $product->setSlug($data['slug']);

        if (\array_key_exists('images', $data)) {
            $this->syncProductImages($product, $data['images']);
        }

        $this->entityManager->persist($product);
    }

    /**
     * Обновить продукт из данных.
     */
    private function updateProductFromData(Product $product, array $data): void
    {
        if (isset($data['title'])) {
            $product->setTitle($data['title']);
        }

        // setTitle() перегенерирует slug, поэтому применяем slug из XML явно
        if (isset($data['slug'])) {
            $product->setSlug($data['slug']);
        }

        if (isset($data['price'])) {
            $product->setPrice((string) $data['price']);
        }

        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }

        if (isset($data['available'])) {
            $product->setAvailable((int) $data['available']);
        }

        if (isset($data['category']) || isset($data['category_slug'])) {
            $category = $this->findOrCreateCategory($this->extractCategory($data));
            $product->setCategory($category);
        }

        if (\array_key_exists('images', $data)) {
            $this->syncProductImages($product, $data['images']);
        }
    }

    /**
     * Синхронизировать изображения продукта с данными из XML.
     *
     * Если узел <images> присутствует, он считается полным списком:
     * отсутствующие изображения удаляются, новые добавляются.
     */
    private function syncProductImages(Product $product, mixed $images): void
    {
        $paths = $this->normalizeImagePaths($images);

        $existing = [];
        foreach ($product->getImages() as $image) {
            $existing[$image->getPath()] = $image;
        }

        foreach ($existing as $path => $image) {
            if (!\in_array($path, $paths, true)) {
                $product->removeImage($image);
            }
        }

        foreach ($paths as $position => $path) {
            $image = $existing[$path] ?? null;
            if ($image === null) {
                $image = new ProductImage($path);
                $product->addImage($image);
            }
            $image->setPosition($position);
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeImagePaths(mixed $images): array
    {
        if (\is_string($images)) {
            $images = preg_split('/[\r\n,]+/', $images) ?: [];
        }

        if (!\is_array($images)) {
            return [];
        }

        // XmlEncoder может вернуть <images><image>...</image></images>
        // как ['image' => [...]], а XmlProductReader — как список строк.
        if (isset($images['image'])) {
            $images = \is_array($images['image']) ? $images['image'] : [$images['image']];
        }

        $paths = [];
        foreach ($images as $image) {
            if (\is_array($image)) {
                $image = $image['path'] ?? $image['url'] ?? null;
            }

            if (!\is_string($image)) {
                continue;
            }

            $path = trim($image);
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return string|array<string, string>
     */
    private function extractCategory(array $data): string|array
    {
        return $data['category'] ?? $data['category_slug'] ?? 'Uncategorized';
    }

    /**
     * Найти или создать категорию.
     *
     * Внутри одного батча повторно созданные категории переиспользуются
     * из in-memory кеша, чтобы не нарушать unique-constraint по slug.
     *
     * @param string|array<string, string> $value
     */
    private function findOrCreateCategory(string|array $value): Category
    {
        [$identifier, $title, $hasExplicitSlug] = $this->normalizeCategoryValue($value);
        $cacheKey = mb_strtolower($identifier);

        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        // Пытаемся найти по slug
        $category = $this->categoryRepository->findBySlug($identifier);

        if ($category === null) {
            // Пытаемся найти по названию
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c')
                ->from(Category::class, 'c')
                ->where('c.title = :title')
                ->setParameter('title', $title);

            $category = $qb->getQuery()->getOneOrNullResult();
        }

        if ($category === null) {
            // Явный slug из XML валидируем, иначе генерируем его из названия
            $category = $hasExplicitSlug
                ? new Category($title, $identifier)
                : new Category($title);

            $this->entityManager->persist($category);
        }

        $this->categoryCache[$cacheKey] = $category;

        return $category;
    }

    /**
     * @param string|array<string, string> $value
     *
     * @return array{0: string, 1: string, 2: bool} [identifier, title, hasExplicitSlug]
     */
    private function normalizeCategoryValue(string|array $value): array
    {
        if (\is_array($value)) {
            $slug = trim((string) ($value['slug'] ?? $value['category_slug'] ?? ''));
            $title = trim((string) ($value['title'] ?? $value['name'] ?? ''));
            $hasExplicitSlug = $slug !== '';

            if ($slug === '') {
                $slug = $title !== '' ? $title : 'Uncategorized';
            }
        } else {
            $slug = trim($value);
            $title = '';
            $hasExplicitSlug = false;
        }

        if ($title === '') {
            $title = $slug !== '' ? $slug : 'Uncategorized';
        }

        if ($slug === '') {
            $slug = $title;
        }

        return [$slug, $title, $hasExplicitSlug];
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->categoryCache = [];
    }
}
