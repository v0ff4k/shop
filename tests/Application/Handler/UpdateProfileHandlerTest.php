<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\UpdateProfileCommand;
use App\Application\Handler\UpdateProfileHandler;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Password;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Тесты для обработчика команды обновления профиля.
 */
final class UpdateProfileHandlerTest extends TestCase
{
    private MockObject|UserRepositoryInterface $userRepository;
    private MockObject|LoggerInterface $logger;
    private UpdateProfileHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new UpdateProfileHandler($this->userRepository, $this->logger);
    }

    public function testUpdateProfileSuccessfully(): void
    {
        $userId = new \App\Domain\User\ValueObject\UserId('user-123');
        $email = new Email('test@example.com');
        $password = new Password('password123', 'salt123');

        $user = new User($userId, $email, $password);
        $user->setFullName('John Doe');
        $user->setAddress('123 Main St');
        $user->setAdditionalInfo('Some info');

        $this->userRepository
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($user);
        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $command = new UpdateProfileCommand(
            $email->toString(),
            'john.doe@example.com',
            'John Smith',
            '456 Oak Ave',
            'New info',
            null,
            null
        );

        $result = ($this->handler)($command);

        $this->assertTrue($result);
        $this->assertSame('john.doe@example.com', $user->getEmail());
        $this->assertSame('John Smith', $user->getFullName());
        $this->assertSame('456 Oak Ave', $user->getAddress());
        $this->assertSame('New info', $user->getAdditionalInfo());
    }

    public function testUpdateProfileWithPasswordChange(): void
    {
        $userId = new \App\Domain\User\ValueObject\UserId('user-123');
        $email = new Email('test@example.com');
        $password = new Password('password123', 'salt123');

        $user = new User($userId, $email, $password);
        $user->setFullName('John Doe');

        $this->userRepository
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($user);
        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $command = new UpdateProfileCommand(
            $email->toString(),
            'john.doe@example.com',
            'John Smith',
            '456 Oak Ave',
            'New info',
            'newpass123',
            'password123'
        );

        $result = ($this->handler)($command);

        $this->assertTrue($result);
        $this->assertSame('newpass123', $user->getPassword()->toString());
    }

    public function testUpdateProfileWithInvalidCurrentPassword(): void
    {
        $userId = new \App\Domain\User\ValueObject\UserId('user-123');
        $email = new Email('test@example.com');
        $password = new Password('password123', 'salt123');

        $user = new User($userId, $email, $password);

        $this->userRepository
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);
        $this->logger
            ->expects($this->never())
            ->method('info');

        $command = new UpdateProfileCommand(
            $email->toString(),
            null,
            null,
            null,
            null,
            'newpass123',
            'wrongpassword'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Current password is incorrect');

        ($this->handler)($command);
    }

    public function testUpdateProfileWithExistingEmail(): void
    {
        $userId = new \App\Domain\User\ValueObject\UserId('user-123');
        $email = new Email('test@example.com');
        $password = new Password('password123', 'salt123');

        $user = new User($userId, $email, $password);
        $existingUser = new User(
            new \App\Domain\User\ValueObject\UserId('user-456'),
            new Email('existing@example.com'),
            new Password('pass456', 'salt456')
        );

        $this->userRepository
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);
        $this->userRepository
            ->method('findByEmail')
            ->with(new Email('existing@example.com'))
            ->willReturn($existingUser);
        $this->logger
            ->expects($this->never())
            ->method('info');

        $command = new UpdateProfileCommand(
            $email->toString(),
            'existing@example.com',
            null,
            null,
            null,
            null,
            null
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already exists');

        ($this->handler)($command);
    }

    public function testUpdateProfileWithNonExistentUser(): void
    {
        $email = new Email('nonexistent@example.com');

        $this->userRepository
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);
        $this->logger
            ->expects($this->never())
            ->method('info');

        $command = new UpdateProfileCommand(
            $email->toString(),
            null,
            null,
            null,
            null,
            null,
            null
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');

        ($this->handler)($command);
    }
}
