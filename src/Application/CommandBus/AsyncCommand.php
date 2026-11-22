<?php

declare(strict_types=1);

namespace App\Application\CommandBus;

/**
 * Атрибут для пометки команд, которые должны обрабатываться асинхронно.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsyncCommand
{
    public function __construct(
        public readonly string $transport = 'async'
    ) {
    }
}
