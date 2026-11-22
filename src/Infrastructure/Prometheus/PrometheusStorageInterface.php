<?php

declare(strict_types=1);

namespace App\Infrastructure\Prometheus;

/**
 * Интерфейс для хранения метрик Prometheus.
 */
interface PrometheusStorageInterface
{
    /**
     * Зарегистрировать Gauge метрику.
     */
    public function registerGauge(string $name, string $help, array $labels = []): void;

    /**
     * Зарегистрировать Counter метрику.
     */
    public function registerCounter(string $name, string $help, array $labels = []): void;

    /**
     * Зарегистрировать Histogram метрику.
     */
    public function registerHistogram(string $name, string $help, array $buckets, array $labels = []): void;

    /**
     * Установить значение Gauge.
     */
    public function setGauge(string $name, float $value, array $labels = []): void;

    /**
     * Инкрементировать Counter.
     */
    public function incrementCounter(string $name, array $labels = [], float $value = 1.0): void;

    /**
     * Наблюдать значение Histogram.
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void;

    /**
     * Получить Registry.
     */
    public function getRegistry(): \Prometheus\CollectorRegistry;
}
