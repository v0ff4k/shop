<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для асинхронного импорта продуктов из XML.
 *
 * Для больших файлов передаётся путь к уже сохранённому на диске файлу:
 * содержимое XML не сериализуется в сообщение и не загружается в память целиком.
 * Поле $xmlContent оставлено для обратной совместимости/небольших импортов.
 */
final class XmlImportCommand
{
    public function __construct(
        public readonly string $jobId,
        public readonly ?string $filePath = null,
        public readonly string $sellerUserId = '0',
        public readonly ?string $xmlContent = null,
    ) {
    }
}
