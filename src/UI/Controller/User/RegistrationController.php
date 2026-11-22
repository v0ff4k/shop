<?php

declare(strict_types=1);

namespace App\UI\Controller\User;

use App\Application\Command\RegisterUserCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\RegisterUserRequestDto;
use App\UI\Response\RedirectResponse;
use App\UI\Response\ViewResponse;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Контроллер регистрации пользователя.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики, валидации или работы с сессией
 */
final class RegistrationController implements ControllerInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Security $security
    ) {
    }

    /**
     * POST /register - Обработка регистрации.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(RegisterUserRequestDto $dto): RedirectResponse
    {
        // Контроллер ТОЛЬКО передаёт DTO в CommandBus
        // Валидация уже выполнена в RequestDtoResolver
        // Логика регистрации - в RegisterUserHandler
        $this->commandBus->dispatch(
            new RegisterUserCommand(
                username: $dto->username,
                email: $dto->email,
                plainPassword: $dto->plainPassword,
                fullName: $dto->fullName,
                address: $dto->address,
                additionalInfo: $dto->additionalInfo
            ));

        // Возвращаем redirect response
        return new RedirectResponse('user_login', [], 'success', 'Registration successful! Please log in.');
    }

    /**
     * GET /register - Показать форму регистрации.
     */
    public function register(): ViewResponse|RedirectResponse
    {
        // Если пользователь уже авторизован
        if ($this->security->getUser()) {
            return new RedirectResponse('shop_homepage', [], 'info', 'You are already logged in');
        }

        return new ViewResponse('user/registration/register.html.twig');
    }
}
