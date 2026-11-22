<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Query\CartQueryService;
use App\UI\Controller\ControllerInterface;
use App\UI\Response\RedirectResponse;

/**
 * Контроллер очистки корзины.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CartClearController implements ControllerInterface
{
    public function __construct(
        private readonly CartQueryService $cartQueryService
    ) {
    }

    /**
     * POST /cart/clear - Очистить корзину.
     */
    public function __invoke(): RedirectResponse
    {
        $this->cartQueryService->clearCart();

        return new RedirectResponse('shop_cart_index', [], 'success', 'Cart cleared');
    }
}
