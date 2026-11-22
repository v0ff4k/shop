<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для POST /api/refresh.
 *
 * Обязательность токена проверяется в контроллере, чтобы сохранить
 * обратно совместимый ответ `{"error": "Refresh token is required"}`.
 */
final class RefreshTokenRequestDto
{
    #[Assert\Length(max: 4096)]
    public readonly string $refreshToken;

    private function __construct(string $refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }

    public static function fromRequest(Request $request): self
    {
        $content = $request->getContent() !== ''
            ? json_decode($request->getContent(), true)
            : null;
        $content = \is_array($content) ? $content : $request->request->all();

        return new self((string) ($content['refresh_token'] ?? ''));
    }

    public static function fromJson(array $data): self
    {
        return new self((string) ($data['refresh_token'] ?? ''));
    }
}
