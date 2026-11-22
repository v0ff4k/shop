<?php

declare(strict_types=1);

namespace App\UI\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для экспорта метрик Prometheus.
 */
final class MetricsController
{
    public function __construct(
        private readonly CollectorRegistry $registry
    ) {
    }

    /**
     * Экспорт метрик в формате Prometheus.
     */
    public function prometheus(): Response
    {
        $renderer = new RenderTextFormat();
        $result = $renderer->render($this->registry->getMetricFamilySamples());

        return new Response($result, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
