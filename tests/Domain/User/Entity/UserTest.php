<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Entity;

use App\Domain\User\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для доменной сущности User.
 */
final class UserTest extends TestCase
{
    public function testCreateUser(): void
    {
        $user = new User('testuser', 'test@example.com', 'password123');

        $this->assertSame('testuser', $user->getUsername());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertNotSame('password123', $user->getPasswordHash());
        $this->assertTrue($user->verifyPassword('password123'));
        $this->assertSame([User::ROLE_CUSTOMER, User::ROLE_USER], $user->getRoles());
        $this->assertTrue($user->isEnabled());
        $this->assertNotNull($user->getRegisteredAt());
    }

    public function testActivateUser(): void
    {
        $user = new User('testuser', 'test@example.com', 'password123');

        $user->setEnabled(false);
        $this->assertFalse($user->isEnabled());

        $user->setEnabled(true);

        $this->assertTrue($user->isEnabled());
        $this->assertNotNull($user->getUpdatedAt());
    }

    public function testUpdateUserProfile(): void
    {
        $user = new User('testuser', 'test@example.com', 'password123');

        $user->setFullName('John Doe');
        $user->setAddress('Some address');
        $user->setAdditionalInfo('Additional info');

        $this->assertSame('John Doe', $user->getFullName());
        $this->assertSame('Some address', $user->getAddress());
        $this->assertSame('Additional info', $user->getAdditionalInfo());
        $this->assertNotNull($user->getUpdatedAt());
    }

    public function testUserRoles(): void
    {
        $user = new User('testuser', 'test@example.com', 'password123');

        $this->assertSame([User::ROLE_CUSTOMER, User::ROLE_USER], $user->getRoles());
        $this->assertTrue($user->hasRole(User::ROLE_CUSTOMER));

        $user->addRole(User::ROLE_SELLER);
        $this->assertTrue($user->hasRole(User::ROLE_SELLER));

        $user->addRole(User::ROLE_ADMIN);
        $this->assertTrue($user->hasRole(User::ROLE_ADMIN));

        $user->removeRole(User::ROLE_ADMIN);
        $this->assertFalse($user->hasRole(User::ROLE_ADMIN));
    }

    public function testUserFullname(): void
    {
        $user = new User('testuser', 'test@example.com', 'password123');

        $user->setFullName('John Doe');

        $this->assertSame('John Doe', $user->getFullName());
        $this->assertSame('John Doe (test@example.com)', $user->getFullNameAndEmail());

        $user->setFullName(null);
        $this->assertNull($user->getFullName());
    }
}
