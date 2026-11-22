<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\RegisterUserCommand;
use App\Application\Service\EmailNotificationService;
use App\Domain\User\Entity\User;
use App\Domain\User\Event\UserRegisteredEvent;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Обработчик команды регистрации пользователя.
 */
final class RegisterUserHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $eventBus,
        private readonly EmailNotificationService $emailNotification
    ) {
    }

    public function __invoke(RegisterUserCommand $command): User
    {
        $startedAt = microtime(true);
        $this->logger->info('Registering user', ['username' => $command->username, 'email' => $command->email]);

        // Проверяем существование пользователя
        $existingUser = $this->userRepository->findByUsername($command->username);
        if ($existingUser !== null) {
            throw new \InvalidArgumentException('Username already exists');
        }

        $existingEmail = $this->userRepository->findByEmail(
            Email::fromString($command->email)
        );
        if ($existingEmail !== null) {
            throw new \InvalidArgumentException('Email already exists');
        }

        // Создаем пользователя
        $user = new User(
            $command->username,
            $command->email,
            $command->plainPassword,
            $command->roles
        );

        if ($command->fullName !== null) {
            $user->setFullName($command->fullName);
        }

        if ($command->address !== null) {
            $user->setAddress($command->address);
        }

        if ($command->additionalInfo !== null) {
            $user->setAdditionalInfo($command->additionalInfo);
        }

        // Сохраняем пользователя
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Публикуем событие о регистрации
        $this->eventBus->dispatch(UserRegisteredEvent::fromUser($user));

        // Отправляем welcome email (асинхронно)
        $this->emailNotification->sendWelcomeEmail($user->getEmail(), $user->getUsername());

        $this->logger->info('User registered', [
            'id' => $user->getId(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $user;
    }
}
