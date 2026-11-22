<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса регистрации пользователя.
 */
final class RegisterUserRequestDto
{
    #[Assert\NotBlank(message: 'Username is required')]
    #[Assert\Length(min: 3, max: 50, minMessage: 'Username must be at least {{ limit }} characters', maxMessage: 'Username cannot exceed {{ limit }} characters')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_]+$/', message: 'Username can only contain letters, numbers and underscores')]
    #[Assert\Type(type: 'string')]
    public readonly string $username;

    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please enter a valid email address')]
    #[Assert\Length(max: 255)]
    #[Assert\Type(type: 'string')]
    public readonly string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, max: 72, minMessage: 'Password must be at least {{ limit }} characters', maxMessage: 'Password cannot exceed {{ limit }} characters')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter and one number'
    )]
    #[Assert\Type(type: 'string')]
    public readonly string $plainPassword;

    #[Assert\Length(max: 100)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $fullName;

    #[Assert\Length(max: 500)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $address;

    #[Assert\Length(max: 1000)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $additionalInfo;

    private function __construct(
        string $username,
        string $email,
        string $plainPassword,
        ?string $fullName,
        ?string $address,
        ?string $additionalInfo
    ) {
        $this->username = $username;
        $this->email = $email;
        $this->plainPassword = $plainPassword;
        $this->fullName = $fullName;
        $this->address = $address;
        $this->additionalInfo = $additionalInfo;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            username: (string) $request->request->get('username', ''),
            email: (string) $request->request->get('email', ''),
            plainPassword: (string) $request->request->get('plain_password', ''),
            fullName: $request->request->get('full_name'),
            address: $request->request->get('address'),
            additionalInfo: $request->request->get('additional_info')
        );
    }

    public static function fromJson(array $data): self
    {
        return new self(
            username: (string) ($data['username'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            plainPassword: (string) ($data['plain_password'] ?? ''),
            fullName: $data['full_name'] ?? null,
            address: $data['address'] ?? null,
            additionalInfo: $data['additional_info'] ?? null
        );
    }
}
