<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Infrastructure\Redis\RedisCounter;
use App\Infrastructure\Redis\RedisQueue;
use Psr\Log\LoggerInterface;

/**
 * Буферизация просмотров товаров в Redis.
 *
 * Просмотры товара — высокочастотное, но не критичное событие. Поэтому
 * счётчик и очередь живут в Redis, а персистенция в PostgreSQL выполняется
 * отдельной командой `app:flush-product-views` по расписанию.
 */
final class ProductViewTracker
{
    public const QUEUE_NAME = 'product_views';
    private const COUNTER_TTL = 2592000; // 30 дней
    private const MAX_QUEUE_LENGTH = 100000;

    public function __construct(
        private readonly RedisCounter $counter,
        private readonly RedisQueue $queue,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return bool false, если Redis недоступен и нужен синхронный fallback
     */
    public function record(int $productId): bool
    {
        try {
            $this->queue->push(
                self::QUEUE_NAME,
                (string) $productId,
                self::MAX_QUEUE_LENGTH
            );
            $this->counter->increment('product:views:'.$productId, self::COUNTER_TTL);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Product view buffering failed, falling back to DB', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getBufferedViews(int $productId): int
    {
        return $this->counter->get('product:views:'.$productId);
    }
}
