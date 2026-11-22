<?php

declare(strict_types=1);

namespace App\Application\Idempotency;

/**
 * Исключение, выбрасываемое при попытке выполнить дубликат команды.
 */
final class DuplicateCommandException extends \RuntimeException
{
}
