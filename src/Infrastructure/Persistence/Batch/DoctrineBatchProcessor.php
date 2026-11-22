<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Batch;

/**
 * Реализация пакетной обработки через Doctrine ORM.
 */
final class DoctrineBatchProcessor implements BatchProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function process(iterable $entities, callable $processor, int $batchSize = 20): void
    {
        $batchCount = 0;

        foreach ($entities as $entity) {
            $processor($entity);

            if (++$batchCount % $batchSize === 0) {
                $this->flushAndClear();
            }
        }

        // Финальный flush для оставшихся сущностей
        if ($batchCount % $batchSize !== 0) {
            $this->flushAndClear();
        }
    }

    public function saveBatch(iterable $entities, int $batchSize = 20): void
    {
        $batchCount = 0;

        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);

            if (++$batchCount % $batchSize === 0) {
                $this->flushAndClear();
            }
        }

        if ($batchCount % $batchSize !== 0) {
            $this->flushAndClear();
        }
    }

    public function deleteBatch(iterable $entities, int $batchSize = 20): void
    {
        $batchCount = 0;

        foreach ($entities as $entity) {
            $this->entityManager->remove($entity);

            if (++$batchCount % $batchSize === 0) {
                $this->flushAndClear();
            }
        }

        if ($batchCount % $batchSize !== 0) {
            $this->flushAndClear();
        }
    }

    /**
     * Обработать сущности с прогрессом
     *
     * @param callable|null $progressCallback Вызывается после каждого пакета: function(int $processed, int $batchSize)
     */
    public function processWithProgress(
        iterable $entities,
        callable $processor,
        ?callable $progressCallback = null,
        int $batchSize = 20
    ): void {
        $batchCount = 0;
        $processedCount = 0;

        foreach ($entities as $entity) {
            $processor($entity);
            ++$processedCount;

            if (++$batchCount % $batchSize === 0) {
                $this->flushAndClear();

                if ($progressCallback !== null) {
                    $progressCallback($processedCount, $batchSize);
                }
            }
        }

        if ($batchCount % $batchSize !== 0) {
            $this->flushAndClear();

            if ($progressCallback !== null) {
                $progressCallback($processedCount, $batchCount % $batchSize);
            }
        }
    }

    /**
     * Выполнить flush и очистить EntityManager для экономии памяти.
     */
    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
