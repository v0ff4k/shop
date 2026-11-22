<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\DTO\Request\ProductSearchRequest;
use App\Application\Query\ProductQueryService;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\UI\Controller\ControllerInterface;
use App\UI\Factory\ResponseFactory;
use App\UI\Request\ProductSearchRequestDto;
use App\UI\Response\ViewResponse;

/**
 * Контроллер каталога продуктов.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class ProductIndexController implements ControllerInterface
{
    public function __construct(
        private readonly ProductQueryService $productQueryService,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * GET /products - Каталог товаров.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(ProductSearchRequestDto $dto): ViewResponse
    {
        $request = ProductSearchRequest::fromUiDto($dto);
        $result = $this->productQueryService->search($request);
        $categories = $this->categoryRepository->findAll();

        return new ViewResponse(
            'shop/product/index.html.twig', [
                'products' => ResponseFactory::paginatedResponse(
                    ResponseFactory::productsFromEntities($result['items']),
                    $result['total'],
                    $dto->page,
                    $dto->limit
                ),
                'categories' => ResponseFactory::categoriesFromEntities($categories),
                'filters' => $dto,
            ]
        );
    }
}
