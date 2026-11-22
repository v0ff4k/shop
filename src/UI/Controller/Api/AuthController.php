<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Jwt\JwtService;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\LoginRequestDto;
use App\UI\Request\RefreshTokenRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * API контроллер для аутентификации.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class AuthController implements ControllerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly JwtService $jwtService
    ) {
    }

    public function __invoke(LoginRequestDto $dto): JsonResponse
    {
        // Поиск пользователя по email или username
        $user = $this->userRepository->findByUsername($dto->username);

        if ($user === null) {
            $user = $this->userRepository->findByEmail(
                \App\Domain\User\ValueObject\Email::fromString($dto->username)
            );
        }

        if ($user === null || !$user->verifyPassword($dto->password)) {
            return new JsonResponse(
                [
                    'error' => 'Invalid credentials',
                ], Response::HTTP_UNAUTHORIZED
            );
        }

        if (!$user->isEnabled()) {
            return new JsonResponse(
                [
                    'error' => 'Account is disabled',
                ], Response::HTTP_FORBIDDEN
            );
        }

        // Создаем токены
        $accessToken = $this->jwtService->createToken(
            $user->getEmail(),
            [
                'user_id' => $user->getId(),
                'roles' => $user->getRoles(),
            ]
        );

        $refreshToken = $this->jwtService->createRefreshToken($user->getEmail());

        // Обновляем время последнего входа
        $user->markAsLoggedIn();
        $this->userRepository->save($user);

        return new JsonResponse(
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $this->jwtService->getTokenRemainingTtl($accessToken),
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ],
            ]
        );
    }

    /**
     * POST /api/login - Аутентификация и выдача JWT токена.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function login(LoginRequestDto $dto): JsonResponse
    {
        return $this->__invoke($dto);
    }

    /**
     * POST /api/refresh - Обновление JWT токена.
     */
    public function refresh(RefreshTokenRequestDto $dto): JsonResponse
    {
        if (trim($dto->refreshToken) === '') {
            return new JsonResponse(
                [
                    'error' => 'Refresh token is required',
                ], Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $claims = $this->jwtService->parseToken($dto->refreshToken);

            // Проверяем тип токена
            if (($claims['type'] ?? null) !== 'refresh') {
                return new JsonResponse(
                    [
                        'error' => 'Invalid token type',
                    ], Response::HTTP_BAD_REQUEST
                );
            }

            $user = $this->userRepository->findByEmail(
                \App\Domain\User\ValueObject\Email::fromString($claims['user'])
            );

            if ($user === null || !$user->isEnabled()) {
                return new JsonResponse(
                    [
                        'error' => 'User not found or disabled',
                    ], Response::HTTP_FORBIDDEN
                );
            }

            // Создаем новые токены
            $accessToken = $this->jwtService->createToken(
                $user->getEmail(),
                [
                    'user_id' => $user->getId(),
                    'roles' => $user->getRoles(),
                ]
            );

            $refreshToken = $this->jwtService->createRefreshToken($user->getEmail());

            return new JsonResponse(
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_in' => $this->jwtService->getTokenRemainingTtl($accessToken),
                    'token_type' => 'Bearer',
                ]
            );
        } catch (\Exception $e) {
            return new JsonResponse(
                [
                    'error' => 'Invalid refresh token',
                ], Response::HTTP_UNAUTHORIZED
            );
        }
    }
}
