<?php

declare(strict_types=1);

namespace App\UI\Controller\User;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\UI\Controller\ControllerInterface;
use App\UI\Response\RedirectResponse;
use App\UI\Response\ViewResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Контроллер отображения профиля пользователя.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class ProfileIndexController implements ControllerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Security $security
    ) {
    }

    /**
     * GET /profile - Показать профиль.
     */
    public function __invoke(): ViewResponse|RedirectResponse
    {
        /** @var UserInterface|null $user */
        $user = $this->security->getUser();

        if ($user === null) {
            return new RedirectResponse('user_login', [], 'error', 'Please log in to access your profile');
        }

        // Пробуем найти пользователя в новом репозитории
        $appUser = null;
        if ($user->getEmail() !== null) {
            $appUser = $this->userRepository->findByEmail(
                Email::fromString($user->getEmail())
            );
        }

        // Если не нашли - используем данные из Security
        if ($appUser === null) {
            // Создаём массив данных из Security пользователя
            $appUser = (object) [
                'id' => $user->getUserIdentifier(),
                'username' => $user->getUserIdentifier(),
                'email' => $user->getEmail() ?? '',
                'fullName' => '',
                'address' => '',
                'additionalInfo' => '',
                'roles' => $user->getRoles() ?? ['ROLE_USER'],
                'registeredAt' => new \DateTimeImmutable(),
                'lastLoginAt' => null,
                'enabled' => true,
            ];
        }

        return new ViewResponse(
            'user/profile/index.html.twig', [
                'user' => $appUser,
            ]
        );
    }
}
