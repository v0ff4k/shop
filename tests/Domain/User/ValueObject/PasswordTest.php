<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\Password;
use PHPUnit\Framework\TestCase;

final class PasswordTest extends TestCase
{
    public function testValidPassword(): void
    {
        $password = Password::fromPlain('SecurePass123!');
        $this->assertTrue($password->verify('SecurePass123!'));
    }

    public function testShortPasswordThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');

        Password::fromPlain('short');
    }

    public function testEmptyPasswordThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Password::fromPlain('');
    }

    public function testPasswordHashing(): void
    {
        $plainPassword = 'SecurePass123!';
        $hashedPassword = password_hash($plainPassword, \PASSWORD_ARGON2ID);

        $password = Password::fromHash($hashedPassword);

        $this->assertTrue($password->verify($plainPassword));
        $this->assertSame($hashedPassword, $password->hash());
    }

    public function testVerifyPassword(): void
    {
        $plainPassword = 'SecurePass123!';
        $hashedPassword = password_hash($plainPassword, \PASSWORD_ARGON2ID);

        $password = Password::fromHash($hashedPassword);

        $this->assertTrue($password->verify('SecurePass123!'));
        $this->assertFalse($password->verify('WrongPassword'));
    }

    public function testPasswordEquality(): void
    {
        $hash = password_hash('SecurePass123!', \PASSWORD_ARGON2ID);
        $password1 = Password::fromHash($hash);
        $password2 = Password::fromHash($hash);

        $this->assertTrue($password1->equals($password2));
    }
}
