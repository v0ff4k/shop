<?php

declare(strict_types=1);

namespace App\UI\Controller\User;

use App\UI\Controller\ControllerInterface;
use App\UI\Response\RedirectResponse;
use App\UI\Response\ViewResponse;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Контроллер отображения списка заказов пользователя.
 */
final class UserOrdersController implements ControllerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Security $security
    ) {
    }

    /**
     * GET /profile/orders - Список заказов пользователя.
     */
    public function __invoke(): ViewResponse|RedirectResponse
    {
        /** @var UserInterface|null $user */
        $user = $this->security->getUser();

        if ($user === null) {
            return new RedirectResponse('user_login', [], 'error', 'Please log in to view your orders');
        }

        // Получаем заказы пользователя
        $orders = $this->connection->fetchAllAssociative(
            'SELECT * FROM "order" WHERE user_id = :userId ORDER BY created_at DESC',
            ['userId' => $user->getId()]
        );

        return new ViewResponse(
            'user/orders/index.html.twig', [
                'orders' => $orders,
            ]
        );
    }
}
