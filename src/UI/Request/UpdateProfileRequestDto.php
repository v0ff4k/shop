<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * DTO для запроса обновления профиля пользователя.
 */
final class UpdateProfileRequestDto
{
    #[Assert\Length(max: 100)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $fullName;

    #[Assert\Length(max: 500)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $address;

    #[Assert\Length(max: 1000)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $additionalInfo;

    #[Assert\Email(message: 'Please enter a valid email address')]
    #[Assert\Length(max: 255)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $email;

    #[Assert\Length(min: 8, max: 72)]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter and one number'
    )]
    #[Assert\Type(type: 'string')]
    public readonly ?string $currentPassword;

    #[Assert\Length(min: 8, max: 72)]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'New password must contain at least one uppercase letter, one lowercase letter and one number'
    )]
    #[Assert\Type(type: 'string')]
    public readonly ?string $newPassword;

    private function __construct(
        ?string $fullName,
        ?string $address,
        ?string $additionalInfo,
        ?string $email,
        ?string $currentPassword,
        ?string $newPassword
    ) {
        $this->fullName = $fullName;
        $this->address = $address;
        $this->additionalInfo = $additionalInfo;
        $this->email = $email;
        $this->currentPassword = $currentPassword;
        $this->newPassword = $newPassword;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            fullName: $request->request->get('full_name'),
            address: $request->request->get('address'),
            additionalInfo: $request->request->get('additional_info'),
            email: $request->request->get('email'),
            currentPassword: $request->request->get('current_password'),
            newPassword: $request->request->get('new_password')
        );
    }
}
