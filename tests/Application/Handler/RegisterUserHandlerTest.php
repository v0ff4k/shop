<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\RegisterUserCommand;
use App\Application\Handler\RegisterUserHandler;
use App\Domain\User\Entity\User;
use App\Domain\User\Event\UserRegisteredEvent;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Тесты для RegisterUserHandler.
 *
 * @covers \App\Application\Handler\RegisterUserHandler
 */
final class RegisterUserHandlerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private UserRepositoryInterface|MockObject $userRepository;
    private LoggerInterface|MockObject $logger;
    private MessageBusInterface|MockObject $eventBus;
    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new RegisterUserHandler(
            $this->entityManager,
            $this->userRepository,
            $this->logger,
            $this->eventBus
        );
    }

    public function testRegisterUser(): void
    {
        // Arrange
        $command = new RegisterUserCommand(
            username: 'testuser',
            email: 'test@example.com',
            plainPassword: 'SecurePassword123!',
            roles: ['ROLE_CUSTOMER']
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->with('testuser')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($this->equalTo(Email::fromString('test@example.com')))
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->eventBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(UserRegisteredEvent::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('testuser', $result->getUsername());
        $this->assertEquals('test@example.com', $result->getEmail()->toString());
        $this->assertEquals(['ROLE_CUSTOMER'], $result->getRoles());
    }

    public function testRegisterUserWithAdditionalFields(): void
    {
        // Arrange
        $command = new RegisterUserCommand(
            username: 'fulluser',
            email: 'full@example.com',
            plainPassword: 'SecurePassword123!',
            roles: ['ROLE_SELLER'],
            fullName: 'John Doe',
            address: '123 Main St',
            additionalInfo: 'VIP customer'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->eventBus
            ->expects($this->once())
            ->method('dispatch');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('John Doe', $result->getFullName());
        $this->assertEquals('123 Main St', $result->getAddress());
        $this->assertEquals('VIP customer', $result->getAdditionalInfo());
    }

    public function testRegisterUserWithExistingUsername(): void
    {
        // Arrange
        $command = new RegisterUserCommand(
            username: 'existinguser',
            email: 'test@example.com',
            plainPassword: 'SecurePassword123!',
            roles: ['ROLE_CUSTOMER']
        );

        $existingUser = $this->createMock(User::class);

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->with('existinguser')
            ->willReturn($existingUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username already exists');

        // Act
        $this->handler($command);
    }

    public function testRegisterUserWithExistingEmail(): void
    {
        // Arrange
        $command = new RegisterUserCommand(
            username: 'newuser',
            email: 'existing@example.com',
            plainPassword: 'SecurePassword123!',
            roles: ['ROLE_CUSTOMER']
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($this->equalTo(Email::fromString('existing@example.com')))
            ->willReturn($this->createMock(User::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already exists');

        // Act
        $this->handler($command);
    }

    public function testEventBusDispatchedWithCorrectEvent(): void
    {
        // Arrange
        $command = new RegisterUserCommand(
            username: 'testuser',
            email: 'test@example.com',
            plainPassword: 'SecurePassword123!',
            roles: ['ROLE_CUSTOMER']
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->handler($command);

        // Assert
        $this->eventBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function ($event) {
                return $event instanceof UserRegisteredEvent;
            }));
    }

    public function testLoggerContext(): void
    {
        // Arrange
        $command = new RegisterUserCommand(
            username: 'logtest',
            email: 'logtest@example.com',
            plainPassword: 'SecurePassword123!',
            roles: ['ROLE_CUSTOMER']
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->eventBus
            ->expects($this->once())
            ->method('dispatch');

        // Act
        $this->handler($command);

        // Assert
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Registering user'),
                $this->callback(static fn ($context) => isset($context['username'])
                    && $context['username'] === 'logtest'
                    && isset($context['email'])
                    && $context['email'] === 'logtest@example.com'
                )
            );
    }
}
