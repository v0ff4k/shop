<?php

declare(strict_types=1);

namespace App\Infrastructure\Cart;

use App\Domain\User\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Слушатель успешного логина для объединения корзин.
 *
 * При входе пользователя объединяет его анонимную корзину (по session ID)
 * с корзиной пользователя (по user ID).
 *
 * Это позволяет:
 * - Сохранить товары, добавленные в корзину до авторизации
 * - Сделать корзину доступной со всех устройств после логина
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final class CartMergeListener
{
    public function __construct(
        private readonly RedisCartStorage $cartStorage,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        // Пропускаем, если пользователь не авторизован (анонимный логин)
        if (!$user instanceof User) {
            return;
        }

        $session = $request->getSession();

        // Пропускаем, если сессии нет
        if (!$session || !$session->isStarted()) {
            return;
        }

        $sessionId = $session->getId();
        $userId = $user->getId();

        // Идентификаторы корзин
        $anonymousCartKey = 'anonymous_'.$sessionId;
        $userCartKey = 'user_'.$userId;

        // Проверяем, есть ли анонимная корзина
        $anonymousCart = $this->cartStorage->getCart($anonymousCartKey);

        if (empty($anonymousCart)) {
            // Анонимной корзины нет — ничего не объединяем
            return;
        }

        // Объединяем корзины
        $this->cartStorage->mergeCarts($anonymousCartKey, $userCartKey);
    }
}
