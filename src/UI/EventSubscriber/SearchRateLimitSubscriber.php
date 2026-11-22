<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use App\Infrastructure\Redis\RedisRateLimiter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Rate limiting для поиска.
 *
 * - Анонимные пользователи: 2 запроса в минуту
 * - Авторизованные пользователи: 1 запрос в 3 секунды
 */
final class SearchRateLimitSubscriber implements EventSubscriberInterface
{
    private const SEARCH_ROUTE = 'shop_product_search';
    private const ANONYMOUS_LIMIT = 2;
    private const ANONYMOUS_TTL = 60;
    private const AUTHENTICATED_TTL = 3;

    public function __construct(
        private readonly RedisRateLimiter $rateLimiter,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Только для GET запросов поиска
        if (!$event->isMainRequest()
            || $request->getMethod() !== 'GET'
            || $request->attributes->get('_route') !== self::SEARCH_ROUTE
        ) {
            return;
        }

        $isAuthenticated = $this->isAuthenticated();

        $clientIp = $request->getClientIp();
        $sessionId = $request->getSession()?->getId() ?? '';
        $userAgent = $request->headers->get('User-Agent', '');

        // Уникальный идентификатор: сессия (если есть) + IP + User-Agent
        $fingerprint = $sessionId ?: md5("{$clientIp}:{$userAgent}");

        if ($isAuthenticated) {
            $this->checkAuthenticatedLimit($fingerprint);
        } else {
            $this->checkAnonymousLimit($fingerprint);
        }
    }

    private function isAuthenticated(): bool
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return false;
        }

        return $token->getUserIdentifier() !== null;
    }

    private function checkAnonymousLimit(string $fingerprint): void
    {
        $result = $this->rateLimiter->hit(
            'search:anonymous:'.$fingerprint,
            self::ANONYMOUS_LIMIT,
            self::ANONYMOUS_TTL
        );

        if (!$result->allowed) {
            $this->throwRateLimitExceeded($result->retryAfter, self::ANONYMOUS_LIMIT, self::ANONYMOUS_TTL);
        }
    }

    private function checkAuthenticatedLimit(string $fingerprint): void
    {
        $userId = $this->tokenStorage->getToken()?->getUserIdentifier() ?? '';

        $result = $this->rateLimiter->hit(
            'search:auth:'.md5("{$userId}:{$fingerprint}"),
            1,
            self::AUTHENTICATED_TTL
        );

        if (!$result->allowed) {
            $this->throwRateLimitExceeded($result->retryAfter, 1, self::AUTHENTICATED_TTL);
        }
    }

    private function throwRateLimitExceeded(int $retryAfter, int $limit, int $period): void
    {
        throw new HttpException(429, \sprintf('Слишком частый поиск. Ограничение: %d за %d сек.. Попробуйте по прошествию %d сек.', $limit, $period, $retryAfter), null, ['Retry-After' => (string) $retryAfter]);
    }
}
