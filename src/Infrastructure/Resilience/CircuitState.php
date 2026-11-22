<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

/**
 * Состояния Circuit Breaker.
 */
enum CircuitState: string
{
    /**
     * Нормальное состояние - запросы проходят
     */
    case CLOSED = 'closed';

    /**
     * Circuit разорван - запросы блокируются.
     */
    case OPEN = 'open';

    /**
     * Полуоткрытое состояние - тестовый запрос
     */
    case HALF_OPEN = 'half_open';
}
