<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ProcessUserImageCommand;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Storage\FileUploadService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Обработчик асинхронной загрузки аватара пользователя.
 */
final class ProcessUserImageHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessUserImageCommand $command): void
    {
        $startedAt = microtime(true);

        $userId = (int) $command->userId;
        $user = $userId > 0 ? $this->userRepository->findById(UserId::fromInt($userId)) : null;
        if ($user === null) {
            $this->logger->error('User not found for image processing', [
                'user_id' => $command->userId,
            ]);

            return;
        }

        try {
            $uploadedFile = new UploadedFile(
                $command->filePath,
                $command->originalName,
                null,
                null,
                true
            );

            $this->fileUploadService->validate($uploadedFile);
            $user->setAvatar($this->fileUploadService->uploadUserImage($uploadedFile, $userId));
            $this->userRepository->save($user);

            $this->logger->info('User image processed', [
                'user_id' => $userId,
                'path' => $user->getAvatar(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process user image', [
                'user_id' => $command->userId,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        }
    }
}
