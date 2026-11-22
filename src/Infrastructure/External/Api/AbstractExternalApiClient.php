<?php

declare(strict_types=1);

namespace App\Infrastructure\External\Api;

use App\Infrastructure\Resilience\CircuitBreakerInterface;
use App\Infrastructure\Resilience\CircuitBreakerOpenException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class AbstractExternalApiClient
{
    protected HttpClientInterface $httpClient;
    protected CircuitBreakerInterface $circuitBreaker;
    protected LoggerInterface $logger;
    protected string $baseUrl;
    protected int $timeout;
    protected array $defaultOptions;

    public function __construct(
        HttpClientInterface $httpClient,
        CircuitBreakerInterface $circuitBreaker,
        LoggerInterface $logger,
        string $baseUrl,
        int $timeout = 10,
        array $defaultOptions = [],
    ) {
        $this->httpClient = $httpClient;
        $this->circuitBreaker = $circuitBreaker;
        $this->logger = $logger;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->defaultOptions = array_merge([
            'timeout' => $this->timeout,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ], $defaultOptions);
    }

    /**
     * Выполняет GET запрос с защитой circuit breaker.
     */
    protected function get(string $endpoint, array $options = [], mixed $fallback = null): mixed
    {
        return $this->executeRequest('GET', $endpoint, $options, $fallback);
    }

    /**
     * Выполняет запрос с retry логикой.
     */
    protected function executeRequest(
        string $method,
        string $endpoint,
        array $options = [],
        mixed $fallback = null,
        int $maxRetries = 3,
    ): mixed {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');
        $mergedOptions = $this->mergeOptions($options);

        $this->logger->info(
            'External API request',
            [
                'method' => $method,
                'url' => $url,
                'service' => $this->getServiceName(),
            ]
        );

        try {
            return $this->circuitBreaker->execute(
                function () use ($method, $url, $mergedOptions, $maxRetries) {
                    return $this->executeWithRetry($method, $url, $mergedOptions, $maxRetries);
                },
                $fallback
            );
        } catch (CircuitBreakerOpenException $e) {
            $this->logger->warning(
                'Circuit breaker is open',
                [
                    'service' => $this->getServiceName(),
                    'message' => $e->getMessage(),
                ]
            );

            if ($fallback !== null) {
                if (\is_callable($fallback)) {
                    return $fallback($e);
                }

                return $fallback;
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error(
                'External API request failed', [
                    'service' => $this->getServiceName(),
                    'url' => $url,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

            if ($fallback !== null) {
                if (\is_callable($fallback)) {
                    return $fallback($e);
                }

                return $fallback;
            }

            throw $e;
        }
    }

    /**
     * Возвращает имя сервиса для circuit breaker.
     */
    abstract protected function getServiceName(): string;

    /**
     * Обрабатывает HTTP ответ
     */
    protected function processResponse(ResponseInterface $response): mixed
    {
        $contentType = $response->getHeaders()['content-type'][0] ?? '';

        if (str_contains($contentType, 'application/json')) {
            return $response->toArray();
        }

        return $response->getContent();
    }

    /**
     * Выполняет POST запрос с защитой circuit breaker.
     */
    protected function post(string $endpoint, array $data = [], array $options = [], mixed $fallback = null): mixed
    {
        $options['json'] = $data;

        return $this->executeRequest('POST', $endpoint, $options, $fallback);
    }

    /**
     * Выполняет PUT запрос с защитой circuit breaker.
     */
    protected function put(string $endpoint, array $data = [], array $options = [], mixed $fallback = null): mixed
    {
        $options['json'] = $data;

        return $this->executeRequest('PUT', $endpoint, $options, $fallback);
    }

    /**
     * Выполняет DELETE запрос с защитой circuit breaker.
     */
    protected function delete(string $endpoint, array $options = [], mixed $fallback = null): mixed
    {
        return $this->executeRequest('DELETE', $endpoint, $options, $fallback);
    }

    /**
     * Объединяет опции по умолчанию с пользовательскими.
     */
    private function mergeOptions(array $options): array
    {
        $merged = $this->defaultOptions;

        if (isset($options['headers'])) {
            $merged['headers'] = array_merge($merged['headers'], $options['headers']);
            unset($options['headers']);
        }

        return array_merge($merged, $options);
    }

    /**
     * Выполняет запрос с retry логикой.
     */
    private function executeWithRetry(
        string $method,
        string $url,
        array $options,
        int $maxRetries,
        int $retryDelay = 1000,
    ): mixed {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    return $this->processResponse($response);
                }

                if ($statusCode >= 400 && $statusCode < 500) {
                    throw new ExternalApiException(\sprintf('Client error: %d', $statusCode), $statusCode);
                }

                throw new ExternalApiException(\sprintf('Server error: %d', $statusCode), $statusCode);
            } catch (\Throwable $e) {
                $lastException = $e;
                ++$attempt;

                if ($attempt < $maxRetries) {
                    $this->logger->warning(
                        'Retrying request',
                        [
                            'attempt' => $attempt,
                            'max_retries' => $maxRetries,
                            'delay_ms' => $retryDelay * $attempt,
                        ]
                    );
                    usleep($retryDelay * $attempt * 1000);
                }
            }
        }

        throw $lastException ?? new ExternalApiException('Request failed after retries', 500);
    }
}
