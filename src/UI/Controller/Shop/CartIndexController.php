<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Query\CartQueryService;
use App\UI\Controller\ControllerInterface;
use App\UI\Response\ViewResponse;

/**
 * Контроллер отображения корзины.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CartIndexController implements ControllerInterface
{
    public function __construct(
        private readonly CartQueryService $cartQueryService
    ) {
    }

    /**
     * GET /cart - Показать корзину.
     */
    public function __invoke(): ViewResponse
    {
        $cartData = $this->cartQueryService->getCartData();

        return new ViewResponse(
            'shop/cart/index.html.twig', [
                'items' => $cartData['items'],
                'total' => $cartData['total'],
            ]
        );
    }
}
