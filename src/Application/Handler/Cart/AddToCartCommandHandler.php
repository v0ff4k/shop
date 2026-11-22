<?php

declare(strict_types=1);

namespace App\Application\Handler\Cart;

use App\Application\Command\Cart\AddToCartCommand;
use App\Infrastructure\Cart\CartStorageInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler для добавления товара в корзину
 * Проверяет существование товара и обновляет корзину.
 *
 * Идентификатор корзины:
 * - Для авторизованных: user_{userId}
 * - Для анонимных: anonymous_{sessionId}
 */
#[AsMessageHandler]
final class AddToCartCommandHandler
{
    private const CART_USER_PREFIX = 'user_';
    private const CART_ANON_PREFIX = 'anonymous_';

    public function __construct(
        private readonly Connection $connection,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly CartStorageInterface $cartStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AddToCartCommand $command): void
    {
        $startedAt = microtime(true);
        // Проверяем существование товара через DBAL (без загрузки entity)
        $product = $this->connection->fetchAssociative(
            'SELECT id, slug, available FROM product WHERE id = :id',
            ['id' => $command->productId]
        );

        if ($product === false) {
            throw new NotFoundHttpException(\sprintf('Product with id "%d" not found', $command->productId));
        }

        if ($product['available'] < $command->quantity) {
            throw new \InvalidArgumentException(\sprintf('Not enough products in stock. Available: %d, Requested: %d', $product['available'], $command->quantity));
        }

        // Определяем идентификатор корзины
        $user = $this->security->getUser();
        $cartIdentifier = $user !== null
            ? self::CART_USER_PREFIX.$user->getId()
            : self::CART_ANON_PREFIX.($this->requestStack->getSession()?->getId() ?? bin2hex(random_bytes(16)));

        // Обновляем корзину в Redis
        $cart = $this->cartStorage->getCart($cartIdentifier);
        $cart[$command->productId] = ($cart[$command->productId] ?? 0) + $command->quantity;
        $this->cartStorage->setCart($cartIdentifier, $cart);

        $this->logger->info('Product added to cart', [
            'product_id' => $command->productId,
            'quantity' => $command->quantity,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
