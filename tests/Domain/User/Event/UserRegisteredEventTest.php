<?php

declare(strict_types=1);

namespace Tests\Domain\User\Event;

use App\Domain\User\Entity\User;
use App\Domain\User\Event\UserRegisteredEvent;
use PHPUnit\Framework\TestCase;

final class UserRegisteredEventTest extends TestCase
{
    public function testEventCreation(): void
    {
        $user = new User('testuser', 'test@example.com', 'password123');

        $property = new \ReflectionProperty($user, 'id');
        $property->setAccessible(true);
        $property->setValue($user, 17);

        $event = UserRegisteredEvent::fromUser($user);

        self::assertSame(17, $event->userId);
        self::assertSame('testuser', $event->username);
        self::assertSame('test@example.com', $event->email);
        self::assertContains('ROLE_USER', $event->roles);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }
}
