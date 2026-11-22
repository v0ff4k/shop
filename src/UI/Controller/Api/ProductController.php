<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\UI\Request\ProductListRequestDto;
use App\UI\Request\ProductShowRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * API контроллер для продуктов.
 *
 * Принимает только DTO, делегирует выборку репозиторию и возвращает JSON.
 */
final class ProductController
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * GET /api/products - Список продуктов.
     */
    public function index(ProductListRequestDto $dto): JsonResponse
    {
        if ($dto->hasSearch()) {
            $products = $this->productRepository->search($dto->search, $dto->limit);
        } elseif ($dto->hasCategory()) {
            $products = $this->productRepository->findByCategory(
                $dto->categoryId,
                $dto->limit,
                $dto->offset
            );
        } else {
            $products = $this->productRepository->findAll($dto->limit, $dto->offset);
        }

        $data = array_map(
            static fn (Product $product): array => [
                'id' => $product->getId(),
                'title' => $product->getTitle(),
                'slug' => $product->getSlug(),
                'price' => $product->getPrice(),
                'available' => $product->getAvailable(),
                'category' => $product->getCategory() !== null
                    ? [
                        'id' => $product->getCategory()->getId(),
                        'title' => $product->getCategory()->getTitle(),
                    ]
                    : null,
                'added' => $product->getAdded()?->format('Y-m-d H:i:s'),
            ],
            $products
        );

        return new JsonResponse([
            'products' => $data,
            'total' => $this->productRepository->count(),
        ]);
    }

    /**
     * GET /api/product/{id} - Детальная информация о продукте.
     */
    public function show(ProductShowRequestDto $dto): JsonResponse
    {
        $product = $this->productRepository->findById(
            ProductId::fromInt($dto->id)
        );

        if ($product === null) {
            return new JsonResponse(
                ['error' => 'Product not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $category = $product->getCategory();

        return new JsonResponse([
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'available' => $product->getAvailable(),
            'category' => $category !== null
                ? [
                    'id' => $category->getId(),
                    'title' => $category->getTitle(),
                    'slug' => $category->getSlug(),
                ]
                : null,
            'viewed' => $product->getViewed(),
            'added' => $product->getAdded()?->format('Y-m-d H:i:s'),
            'lastViewed' => $product->getLastViewed()?->format('Y-m-d H:i:s'),
        ]);
    }
}
