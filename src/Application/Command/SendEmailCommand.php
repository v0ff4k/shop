<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для отправки email (асинхронная).
 */
final class SendEmailCommand
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $htmlBody = null,
        public readonly ?array $context = null,
        public readonly ?string $transport = null
    ) {
    }
}
