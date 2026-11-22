<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Query\ProductQueryService;
use App\Application\Service\ProductViewTracker;
use App\UI\Controller\ControllerInterface;
use App\UI\Factory\ResponseFactory;
use App\UI\Request\ProductViewRequestDto;
use App\UI\Response\ViewResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Контроллер просмотра продукта.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class ProductShowController implements ControllerInterface
{
    public function __construct(
        private readonly ProductQueryService $productQueryService,
        private readonly ProductViewTracker $productViewTracker
    ) {
    }

    /**
     * GET /product/{slug} - Просмотр товара.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(ProductViewRequestDto $dto): ViewResponse
    {
        /** @var \App\Domain\Shop\Entity\Product $product */
        $product = $this->productQueryService->findBySlug($dto->slug);

        if ($product === null) {
            throw new NotFoundHttpException('Product not found');
        }

        // Буферизуем просмотр в Redis; при недоступности Redis — синхронный fallback.
        if (!$this->productViewTracker->record((int) $product->getId())) {
            $this->productQueryService->incrementViewed($product);
        }

        return new ViewResponse(
            'shop/product/show.html.twig', [
                'product' => ResponseFactory::productFromEntity($product),
            ]
        );
    }
}
