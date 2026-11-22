<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\DTO\Request\ProductSearchRequest;
use App\Application\Query\ProductQueryService;
use App\UI\Controller\ControllerInterface;
use App\UI\Factory\ResponseFactory;
use App\UI\Request\ProductSearchRequestDto;
use App\UI\Response\ViewResponse;

/**
 * Контроллер поиска продуктов.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class ProductSearchController implements ControllerInterface
{
    public function __construct(
        private readonly ProductQueryService $productQueryService
    ) {
    }

    /**
     * GET /search - Поиск товаров.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(ProductSearchRequestDto $dto): ViewResponse
    {
        $request = ProductSearchRequest::fromUiDto($dto);
        $result = $this->productQueryService->search($request);

        return new ViewResponse(
            'shop/product/search.html.twig', [
                'products' => ResponseFactory::paginatedResponse(
                    ResponseFactory::productsFromEntities($result['items']),
                    $result['total'],
                    $dto->page,
                    $dto->limit
                ),
                'query' => $dto->query,
                'total' => $result['total'],
            ]
        );
    }
}
