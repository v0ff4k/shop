<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Command\CreateOrderCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\Application\Query\CartQueryService;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\CreateOrderRequestDto;
use App\UI\Response\RedirectResponse;
use App\UI\Response\ViewResponse;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Контроллер заказов.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики, работы с сессией или валидации
 */
final class OrderController implements ControllerInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CartQueryService $cartQueryService,
        private readonly Security $security
    ) {
    }

    /**
     * POST /order/create - Обработка создания заказа.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(CreateOrderRequestDto $dto): RedirectResponse
    {
        // Контроллер ТОЛЬКО передаёт DTO в CommandBus
        // Валидация уже выполнена в RequestDtoResolver
        // Логика создания заказа - в CreateOrderHandler
        $order = $this->commandBus->dispatch(
            new CreateOrderCommand(
                customerName: $dto->customerName,
                deliveryAddress: $dto->deliveryAddress,
                items: $dto->items,
                additionalInfo: $dto->additionalInfo,
                userId: $this->security->getUser()?->getId(),
                idempotencyKey: $dto->getIdempotencyKey()
            )
        );

        // Очищаем корзину после успешного заказа
        $this->cartQueryService->clearCart();

        // Получаем номер заказа из возвращённого объекта Order
        $orderNumber = $order instanceof \App\Domain\Order\Entity\Order
            ? $order->getOrderNumber()
            : 'UNKNOWN';

        // Возвращаем redirect response
        return new RedirectResponse(
            'shop_order_success',
            ['orderNumber' => $orderNumber],
            'success',
            'Order created successfully!'
        );
    }

    /**
     * GET /order/create - Показать форму оформления заказа.
     */
    public function create(): ViewResponse|RedirectResponse
    {
        // Получаем данные корзины через Query сервис
        $cartData = $this->cartQueryService->getCartData();

        if (empty($cartData['items'])) {
            return new RedirectResponse('shop_cart_index', [], 'warning', 'Your cart is empty');
        }

        return new ViewResponse(
            'shop/order/create.html.twig', [
                'items' => $cartData['items'],
                'total' => $cartData['total'],
            ]
        );
    }

    /**
     * GET /order/success/{orderNumber} - Страница успешного заказа.
     */
    public function success(string $orderNumber): ViewResponse
    {
        return new ViewResponse(
            'shop/order/success.html.twig', [
                'orderNumber' => $orderNumber,
            ]
        );
    }
}
