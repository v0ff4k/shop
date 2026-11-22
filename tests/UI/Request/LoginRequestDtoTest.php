<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\LoginRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Тесты для LoginRequestDto.
 */
final class LoginRequestDtoTest extends TestCase
{
    public function testCreateFromValidJsonRequest(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'test@example.com', 'password' => 'secret123'])
        );

        $dto = LoginRequestDto::fromRequest($request);

        $this->assertSame('test@example.com', $dto->username);
        $this->assertSame('secret123', $dto->password);
        $this->assertFalse($dto->rememberMe);
    }

    public function testCreateFromPostRequest(): void
    {
        $request = new Request(
            ['username' => 'user123', 'password' => 'pass456', 'remember_me' => '1'],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $dto = LoginRequestDto::fromRequest($request);

        $this->assertSame('user123', $dto->username);
        $this->assertSame('pass456', $dto->password);
        $this->assertTrue($dto->rememberMe);
    }

    public function testCreateFromJson(): void
    {
        $data = [
            'username' => 'john@example.com',
            'password' => 'password123',
            'remember_me' => true,
        ];

        $dto = LoginRequestDto::fromJson($data);

        $this->assertSame('john@example.com', $dto->username);
        $this->assertSame('password123', $dto->password);
        $this->assertTrue($dto->rememberMe);
    }

    public function testCreateWithMissingData(): void
    {
        $data = [];

        $dto = LoginRequestDto::fromJson($data);

        $this->assertSame('', $dto->username);
        $this->assertSame('', $dto->password);
        $this->assertFalse($dto->rememberMe);
    }

    public function testRememberMeDefaultsToFalse(): void
    {
        $request = new Request(
            ['username' => 'user', 'password' => 'pass'],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $dto = LoginRequestDto::fromRequest($request);

        $this->assertFalse($dto->rememberMe);
    }
}
