<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Query\CartQueryService;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\RemoveFromCartRequestDto;
use App\UI\Response\RedirectResponse;

/**
 * Контроллер удаления из корзины.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CartRemoveController implements ControllerInterface
{
    public function __construct(
        private readonly CartQueryService $cartQueryService
    ) {
    }

    /**
     * POST /cart/remove/{productId} - Удалить из корзины.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(RemoveFromCartRequestDto $dto): RedirectResponse
    {
        $this->cartQueryService->removeFromCart($dto->productId);

        return new RedirectResponse('shop_cart_index', [], 'success', 'Product removed from cart');
    }
}
