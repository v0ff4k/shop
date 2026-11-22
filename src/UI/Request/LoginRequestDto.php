<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса аутентификации.
 */
final class LoginRequestDto
{
    #[Assert\NotBlank(message: 'Username or email is required')]
    #[Assert\Length(max: 255)]
    #[Assert\Type(type: 'string')]
    public readonly string $username;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(max: 72)]
    #[Assert\Type(type: 'string')]
    public readonly string $password;

    #[Assert\Type(type: 'boolean')]
    public readonly bool $rememberMe;

    private function __construct(string $username, string $password, bool $rememberMe)
    {
        $this->username = $username;
        $this->password = $password;
        $this->rememberMe = $rememberMe;
    }

    public static function fromRequest(Request $request): self
    {
        $content = $request->getContent() !== ''
            ? json_decode($request->getContent(), true)
            : null;
        $content = \is_array($content) ? $content : $request->request->all();
        $content = $content !== [] ? $content : $request->query->all();

        return new self(
            username: (string) ($content['username'] ?? ''),
            password: (string) ($content['password'] ?? ''),
            rememberMe: (bool) ($content['remember_me'] ?? false)
        );
    }

    public static function fromJson(array $data): self
    {
        return new self(
            username: (string) ($data['username'] ?? ''),
            password: (string) ($data['password'] ?? ''),
            rememberMe: (bool) ($data['remember_me'] ?? false)
        );
    }
}
