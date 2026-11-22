<?php

declare(strict_types=1);

namespace App\Infrastructure\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

/**
 * Хранилище для метрик Prometheus на основе Redis.
 */
final class PrometheusStorage implements PrometheusStorageInterface
{
    private CollectorRegistry $registry;

    public function __construct(CollectorRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }

    /**
     * Создать или получить счетчик.
     */
    public function createCounter(string $name, string $help, array $labels = []): Counter
    {
        return $this->registry->getOrRegisterCounter('app', $name, $help, $labels);
    }

    /**
     * Создать или получить gauge.
     */
    public function createGauge(string $name, string $help, array $labels = []): Gauge
    {
        return $this->registry->getOrRegisterGauge('app', $name, $help, $labels);
    }

    /**
     * Создать или получить histogram.
     */
    public function createHistogram(
        string $name,
        string $help,
        array $labels = [],
        ?array $buckets = null
    ): Histogram {
        return $this->registry->getOrRegisterHistogram('app', $name, $help, $labels, $buckets);
    }

    // Реализация методов интерфейса

    public function registerGauge(string $name, string $help, array $labels = []): void
    {
        $this->createGauge($name, $help, $labels);
    }

    public function registerCounter(string $name, string $help, array $labels = []): void
    {
        $this->createCounter($name, $help, $labels);
    }

    public function registerHistogram(string $name, string $help, array $buckets, array $labels = []): void
    {
        $this->createHistogram($name, $help, $labels, $buckets);
    }

    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $this->createGauge($name, '', array_keys($labels))->set($value, $labels);
    }

    public function incrementCounter(string $name, array $labels = [], float $value = 1.0): void
    {
        $this->createCounter($name, '', array_keys($labels))->incBy($value, $labels);
    }

    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $this->createHistogram($name, '', array_keys($labels))->observe($value, $labels);
    }
}
