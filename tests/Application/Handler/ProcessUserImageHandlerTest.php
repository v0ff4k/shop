<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\ProcessUserImageCommand;
use App\Application\Handler\ProcessUserImageHandler;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Helper\PathHelper;
use App\Infrastructure\Storage\FileUploadService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProcessUserImageHandlerTest extends TestCase
{
    public function testUploadsUserAvatarAndSavesUser(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'user_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true
        ));

        $userStorage = $this->createMock(FilesystemOperator::class);
        $userStorage->method('directoryExists')->willReturn(false);
        $userStorage->expects(self::once())->method('createDirectory');
        $userStorage->expects(self::once())->method('write');

        $fileUploadService = new FileUploadService(
            $this->createMock(FilesystemOperator::class),
            $this->createMock(FilesystemOperator::class),
            $userStorage,
            new PathHelper()
        );

        $user = new User('avatar_user', 'avatar@example.com', 'password123');
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findById')->willReturn($user);
        $repository->expects(self::once())->method('save')->with($user);

        $handler = new ProcessUserImageHandler(
            $repository,
            $fileUploadService,
            $this->createMock(LoggerInterface::class)
        );

        $handler(new ProcessUserImageCommand('1', $tmpFile, 'avatar.png'));

        self::assertNotNull($user->getAvatar());
        self::assertStringContainsString('user_1_', basename((string) $user->getAvatar()));

        @unlink($tmpFile);
    }
}
