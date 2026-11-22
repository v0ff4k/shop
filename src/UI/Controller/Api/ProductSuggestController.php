<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Infrastructure\Search\ProductSearchService;
use App\UI\Request\ProductSuggestRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API контроллер автодополнения поиска продуктов.
 */
final class ProductSuggestController
{
    public function __construct(
        private readonly ProductSearchService $productSearchService
    ) {
    }

    /**
     * GET /api/products/suggest - Подсказки для поиска.
     */
    public function __invoke(ProductSuggestRequestDto $dto): JsonResponse
    {
        return new JsonResponse([
            'suggestions' => $this->productSearchService->suggest($dto->query, $dto->limit),
        ]);
    }
}
