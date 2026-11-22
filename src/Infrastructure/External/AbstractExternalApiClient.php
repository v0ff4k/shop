<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

use App\Infrastructure\Resilience\CircuitBreakerFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Базовый класс для внешних API клиентов с Circuit Breaker.
 */
abstract class AbstractExternalApiClient
{
    protected readonly HttpClientInterface $httpClient;
    protected readonly CircuitBreakerInterface $circuitBreaker;

    public function __construct(
        CircuitBreakerFactory $circuitBreakerFactory,
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?string $baseUrl = null,
        ?int $timeout = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create([
            'timeout' => $timeout ?? 10,
            'max_redirects' => 3,
            'verify_peer' => true,
            'verify_host' => true,
        ]);

        $serviceName = $this->getServiceName();
        $this->circuitBreaker = $circuitBreakerFactory->get($serviceName);
    }

    /**
     * Получить имя сервиса для Circuit Breaker.
     */
    abstract protected function getServiceName(): string;

    /**
     * Выполнить запрос с защитой Circuit Breaker.
     *
     * @template T
     *
     * @param callable(): T $operation
     * @param callable(\Throwable): T|null $fallback
     *
     * @return T
     */
    protected function executeWithCircuitBreaker(
        callable $operation,
        ?callable $fallback = null
    ): mixed {
        return $this->circuitBreaker->execute($operation, $fallback);
    }

    /**
     * Получить базовый URL сервиса.
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Получить заголовки по умолчанию.
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'Shop-App/1.0',
        ];
    }

    /**
     * Логирование запроса.
     */
    protected function logRequest(string $method, string $url, array $options = []): void
    {
        // Может быть реализовано в подклассах
    }

    /**
     * Логирование ответа.
     */
    protected function logResponse(string $method, string $url, int $statusCode, ?string $body = null): void
    {
        // Может быть реализовано в подклассах
    }
}
