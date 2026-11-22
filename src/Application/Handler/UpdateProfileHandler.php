<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\UpdateProfileCommand;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use Psr\Log\LoggerInterface;

/**
 * Обработчик команды обновления профиля.
 */
final class UpdateProfileHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(UpdateProfileCommand $command): bool
    {
        $startedAt = microtime(true);
        $this->logger->info('Updating user profile', ['email' => $command->userEmail]);

        $user = $this->userRepository->findByEmail(
            Email::fromString($command->userEmail)
        );

        if ($user === null) {
            throw new \InvalidArgumentException('User not found');
        }

        // Обновляем email
        if ($command->email !== null && $command->email !== $user->getEmail()) {
            $existingUser = $this->userRepository->findByEmail(
                Email::fromString($command->email)
            );

            if ($existingUser !== null && $existingUser->getId() !== $user->getId()) {
                throw new \InvalidArgumentException('Email already exists');
            }

            $user->setEmail($command->email);
        }

        // Обновляем профиль
        if ($command->fullName !== null) {
            $user->setFullName($command->fullName);
        }

        if ($command->address !== null) {
            $user->setAddress($command->address);
        }

        if ($command->additionalInfo !== null) {
            $user->setAdditionalInfo($command->additionalInfo);
        }

        // Смена пароля
        if ($command->newPassword !== null) {
            if ($command->currentPassword === null) {
                throw new \InvalidArgumentException('Current password is required');
            }

            if (!$user->verifyPassword($command->currentPassword)) {
                throw new \InvalidArgumentException('Current password is incorrect');
            }

            $user->setPassword($command->newPassword);
        }

        $this->userRepository->save($user);

        $this->logger->info('User profile updated', [
            'user_id' => $user->getId(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return true;
    }
}
