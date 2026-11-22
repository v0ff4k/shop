<?php

declare(strict_types=1);

namespace App\Infrastructure\External\Api;

use App\Infrastructure\Resilience\CircuitBreakerFactory;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Пример внешнего API клиента для сервиса платежей.
 */
class PaymentApiClient extends AbstractExternalApiClient
{
    private CircuitBreakerFactory $circuitBreakerFactory;

    public function __construct(
        HttpClientInterface $httpClient,
        CircuitBreakerFactory $circuitBreakerFactory,
        LoggerInterface $logger,
        string $baseUrl,
        int $timeout = 5,
    ) {
        $this->circuitBreakerFactory = $circuitBreakerFactory;

        parent::__construct(
            $httpClient,
            $circuitBreakerFactory->create('payment_api'),
            $logger,
            $baseUrl,
            $timeout,
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$_ENV['PAYMENT_API_KEY'] ?? '',
                    'X-API-Version' => 'v1',
                ],
            ]
        );
    }

    /**
     * Создает платеж.
     */
    public function createPayment(array $paymentData, mixed $fallback = null): mixed
    {
        return $this->post('/payments', $paymentData, [], $fallback);
    }

    /**
     * Получает статус платежа.
     */
    public function getPaymentStatus(string $paymentId, mixed $fallback = null): mixed
    {
        return $this->get("/payments/{$paymentId}", [], $fallback);
    }

    /**
     * Отменяет платеж.
     */
    public function cancelPayment(string $paymentId, mixed $fallback = null): mixed
    {
        return $this->post("/payments/{$paymentId}/cancel", [], [], $fallback);
    }

    /**
     * Возвращает список платежей.
     */
    public function getPayments(array $filters = [], mixed $fallback = null): mixed
    {
        return $this->get('/payments', ['query' => $filters], $fallback);
    }

    protected function getServiceName(): string
    {
        return 'payment_api';
    }
}
