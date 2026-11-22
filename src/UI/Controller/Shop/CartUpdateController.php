<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Query\CartQueryService;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\UpdateCartRequestDto;
use App\UI\Response\RedirectResponse;

/**
 * Контроллер обновления корзины.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CartUpdateController implements ControllerInterface
{
    public function __construct(
        private readonly CartQueryService $cartQueryService
    ) {
    }

    /**
     * POST /cart/update/{productId} - Обновить количество в корзине.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(UpdateCartRequestDto $dto): RedirectResponse
    {
        $this->cartQueryService->updateCart($dto->productId, $dto->quantity);

        return new RedirectResponse('shop_cart_index', [], 'success', 'Корзина обновлена');
    }
}
