<?php

declare(strict_types=1);

namespace App\UI\Controller\User;

use App\Application\Command\UpdateProfileCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\UpdateProfileRequestDto;
use App\UI\Response\RedirectResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Контроллер обновления профиля пользователя.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class ProfileUpdateController implements ControllerInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Security $security
    ) {
    }

    /**
     * POST /profile - Обновить профиль.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(UpdateProfileRequestDto $dto): RedirectResponse
    {
        /** @var UserInterface|null $user */
        $user = $this->security->getUser();

        if ($user === null) {
            return new RedirectResponse('user_login', [], 'error', 'Please log in to access your profile');
        }

        try {
            $this->commandBus->dispatch(
                new UpdateProfileCommand(
                    $user->getEmail(),
                    $dto->email,
                    $dto->fullName,
                    $dto->address,
                    $dto->additionalInfo,
                    $dto->currentPassword,
                    $dto->newPassword
                )
            );

            return new RedirectResponse('user_profile', [], 'success', 'Profile updated successfully');
        } catch (\InvalidArgumentException $e) {
            return new RedirectResponse('user_profile', [], 'error', $e->getMessage());
        } catch (\Exception $e) {
            return new RedirectResponse('user_profile', [], 'error', 'Failed to update profile');
        }
    }
}
