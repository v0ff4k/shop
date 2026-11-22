<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Batch;

/**
 * Интерфейс для пакетной обработки сущностей.
 */
interface BatchProcessorInterface
{
    /**
     * Обработать сущности пакетно.
     */
    public function process(iterable $entities, callable $processor, int $batchSize = 20): void;

    /**
     * Сохранить сущности пакетно.
     */
    public function saveBatch(iterable $entities, int $batchSize = 20): void;

    /**
     * Удалить сущности пакетно.
     */
    public function deleteBatch(iterable $entities, int $batchSize = 20): void;
}
