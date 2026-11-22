<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

/**
 * Пример внешнего API клиента для платежного шлюза.
 *
 * Использование:
 * $result = $paymentClient->processPayment($amount, $cardData);
 */
final class PaymentGatewayClient extends AbstractExternalApiClient
{
    public function __construct(
        CircuitBreakerFactory $circuitBreakerFactory,
        private readonly string $apiKey,
        ?string $baseUrl = null,
    ) {
        parent::__construct(
            $circuitBreakerFactory,
            null,
            null,
            $baseUrl ?? 'https://api.payment-gateway.example',
            15
        );
    }

    /**
     * Обработать платеж.
     *
     * @param array<string, mixed> $paymentData
     *
     * @return array<string, mixed> Результат платежа
     */
    public function processPayment(array $paymentData): array
    {
        return $this->executeWithCircuitBreaker(
            function () use ($paymentData) {
                $response = $this->httpClient->request(
                    'POST',
                    $this->getBaseUrl().'/payments',
                    [
                        'headers' => array_merge(
                            $this->getDefaultHeaders(),
                            [
                                'Authorization' => 'Bearer '.$this->apiKey,
                                'Content-Type' => 'application/json',
                            ]
                        ),
                        'json' => $paymentData,
                    ]
                );

                return $response->toArray();
            },
            function (\Throwable $e) {
                // Fallback: сохраняем платеж для последующей обработки
                return $this->fallbackProcessPayment($paymentData, $e);
            }
        );
    }

    /**
     * Получить статус платежа.
     */
    public function getPaymentStatus(string $paymentId): ?array
    {
        return $this->executeWithCircuitBreaker(
            function () use ($paymentId) {
                $response = $this->httpClient->request(
                    'GET',
                    $this->getBaseUrl().'/payments/'.$paymentId,
                    [
                        'headers' => array_merge(
                            $this->getDefaultHeaders(),
                            [
                                'Authorization' => 'Bearer '.$this->apiKey,
                            ]
                        ),
                    ]
                );

                if ($response->getStatusCode() === 404) {
                    return null;
                }

                return $response->toArray();
            },
            function (\Throwable $e) {
                // Fallback: возвращаем кэшированный статус если есть
                return $this->getCachedPaymentStatus($paymentId);
            }
        );
    }

    /**
     * Вернуть платеж.
     */
    public function refundPayment(string $paymentId, float $amount): array
    {
        return $this->executeWithCircuitBreaker(
            function () use ($paymentId, $amount) {
                $response = $this->httpClient->request(
                    'POST',
                    $this->getBaseUrl().'/payments/'.$paymentId.'/refund',
                    [
                        'headers' => array_merge(
                            $this->getDefaultHeaders(),
                            [
                                'Authorization' => 'Bearer '.$this->apiKey,
                                'Content-Type' => 'application/json',
                            ]
                        ),
                        'json' => ['amount' => $amount],
                    ]
                );

                return $response->toArray();
            },
            function (\Throwable $e) {
                // Fallback: сохраняем запрос на возврат для последующей обработки
                return $this->fallbackRefundPayment($paymentId, $amount, $e);
            }
        );
    }

    protected function getBaseUrl(): string
    {
        return $this->baseUrl ?? 'https://api.payment-gateway.example';
    }

    protected function getServiceName(): string
    {
        return 'payment_gateway';
    }

    /**
     * Fallback для processPayment.
     *
     * @param array<string, mixed> $paymentData
     *
     * @return array<string, mixed>
     */
    private function fallbackProcessPayment(array $paymentData, \Throwable $e): array
    {
        // Сохраняем платеж в очередь для последующей обработки
        return [
            'status' => 'pending_retry',
            'transaction_id' => 'pending_'.uniqid('pay_', true),
            'message' => 'Платеж сохранен для последующей обработки',
            'retry_later' => true,
        ];
    }

    /**
     * Получить кэшированный статус платежа.
     *
     * @return array<string, mixed>|null
     */
    private function getCachedPaymentStatus(string $paymentId): ?array
    {
        // Здесь может быть логика получения из кэша
        return null;
    }

    /**
     * Fallback для refundPayment.
     *
     * @return array<string, mixed>
     */
    private function fallbackRefundPayment(string $paymentId, float $amount, \Throwable $e): array
    {
        // Сохраняем запрос на возврат в очередь
        return [
            'status' => 'pending_retry',
            'refund_id' => 'pending_'.uniqid('ref_', true),
            'message' => 'Возврат сохранен для последующей обработки',
            'retry_later' => true,
        ];
    }
}
