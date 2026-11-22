<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Атрибут для пометки идемпотентных обработчиков команд.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class IdempotentHandler
{
    public function __construct(
        #[Autowire(env: 'bool:IDEMPOTENCY_ENABLED')]
        public readonly bool $enabled = true,
        public readonly int $ttlSeconds = 86400
    ) {
    }
}
