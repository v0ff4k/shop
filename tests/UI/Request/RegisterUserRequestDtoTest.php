<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\RegisterUserRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Тесты для DTO регистрации пользователя.
 */
final class RegisterUserRequestDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidRegisterUserRequest(): void
    {
        $dto = RegisterUserRequestDto::fromJson($this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testRegisterUserRequestFromRequest(): void
    {
        $request = new Request([], [
            'username' => 'john_doe',
            'email' => 'test@example.com',
            'plain_password' => 'Password123',
            'full_name' => 'John Doe',
            'address' => '123 Main Street',
            'additional_info' => 'VIP customer',
        ]);

        $dto = RegisterUserRequestDto::fromRequest($request);

        $this->assertSame('john_doe', $dto->username);
        $this->assertSame('test@example.com', $dto->email);
        $this->assertSame('Password123', $dto->plainPassword);
        $this->assertSame('John Doe', $dto->fullName);
        $this->assertSame('123 Main Street', $dto->address);
        $this->assertSame('VIP customer', $dto->additionalInfo);
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testRegisterUserRequestRequiresUsername(): void
    {
        $dto = RegisterUserRequestDto::fromJson(['username' => ''] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertSame(
            [
                'Username is required',
                'Username must be at least 3 characters',
            ],
            $this->messages($violations)
        );
    }

    public function testRegisterUserRequestRequiresEmail(): void
    {
        $dto = RegisterUserRequestDto::fromJson(['email' => ''] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Email is required', $violations[0]->getMessage());
    }

    public function testRegisterUserRequestEmailValidation(): void
    {
        $dto = RegisterUserRequestDto::fromJson(['email' => 'invalid-email'] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Please enter a valid email address', $violations[0]->getMessage());
    }

    public function testRegisterUserRequestRequiresPassword(): void
    {
        $dto = RegisterUserRequestDto::fromJson(['plain_password' => ''] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertSame(
            [
                'Password is required',
                'Password must be at least 8 characters',
            ],
            $this->messages($violations)
        );
    }

    public function testRegisterUserRequestPasswordStrength(): void
    {
        $dto = RegisterUserRequestDto::fromJson(['plain_password' => 'weak'] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertSame(
            [
                'Password must be at least 8 characters',
                'Password must contain at least one uppercase letter, one lowercase letter and one number',
            ],
            $this->messages($violations)
        );
    }

    public function testRegisterUserRequestFullNameIsOptional(): void
    {
        $payload = $this->validPayload();
        unset($payload['full_name']);

        $dto = RegisterUserRequestDto::fromJson($payload);

        $this->assertNull($dto->fullName);
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testRegisterUserRequestFullNameLength(): void
    {
        $dto = RegisterUserRequestDto::fromJson([
            'full_name' => str_repeat('a', 101),
        ] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('This value is too long. It should have 100 characters or less.', $violations[0]->getMessage());
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'username' => 'john_doe',
            'email' => 'test@example.com',
            'plain_password' => 'Password123',
            'full_name' => 'John Doe',
        ];
    }

    /**
     * @return string[]
     */
    private function messages(ConstraintViolationListInterface $violations): array
    {
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages;
    }
}
