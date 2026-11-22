<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Command\Cart\AddToCartCommand;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\AddToCartRequestDto;
use App\UI\Response\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Контроллер добавления в корзину.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CartAddController implements ControllerInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus
    ) {
    }

    /**
     * POST /cart/add - Добавить в корзину.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(AddToCartRequestDto $dto): RedirectResponse
    {
        $command = new AddToCartCommand(
            productId: $dto->productId,
            quantity: $dto->quantity,
        );

        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof NotFoundHttpException) {
                throw $previous;
            }

            if ($previous instanceof \InvalidArgumentException) {
                throw new BadRequestHttpException($previous->getMessage(), $previous);
            }

            throw $e;
        }

        return new RedirectResponse('shop_cart_index', [], 'success', 'Добавлено в корзину');
    }
}
