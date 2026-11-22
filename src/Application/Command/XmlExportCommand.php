<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для асинхронного экспорта продуктов в XML.
 */
final class XmlExportCommand
{
    public function __construct(
        public readonly string $jobId,
        public readonly array $productIds,
        public readonly int $limit,
        public readonly ?string $outputPath,
        public readonly string $sellerUserId
    ) {
    }
}
