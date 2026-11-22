<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Service\ProductViewTracker;
use App\Infrastructure\Redis\RedisQueue;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Персистенция буферизованных в Redis просмотров товаров.
 *
 * Запускать по cron раз в минуту:
 *   * * * * * docker exec shop_php php bin/console app:flush-product-views
 */
#[AsCommand(
    name: 'app:flush-product-views',
    description: 'Flush buffered product views from Redis to PostgreSQL'
)]
final class FlushProductViewsCommand extends Command
{
    public function __construct(
        private readonly RedisQueue $queue,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Максимальное количество записей за один запуск',
            '10000'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $counts = [];
        $processed = 0;

        while ($processed < $limit && ($payload = $this->queue->pop(ProductViewTracker::QUEUE_NAME)) !== null) {
            ++$processed;

            if (ctype_digit($payload)) {
                $productId = (int) $payload;
                $counts[$productId] = ($counts[$productId] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            $io->text('No buffered product views.');

            return Command::SUCCESS;
        }

        try {
            $this->connection->beginTransaction();

            foreach ($counts as $productId => $count) {
                $this->connection->executeStatement(
                    'UPDATE product SET viewed = viewed + :count, last_viewed = NOW() WHERE id = :id',
                    ['count' => $count, 'id' => $productId]
                );
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            // Возвращаем необработанные просмотры в очередь, чтобы не потерять данные.
            foreach ($counts as $productId => $count) {
                for ($i = 0; $i < $count; ++$i) {
                    $this->queue->push(ProductViewTracker::QUEUE_NAME, (string) $productId);
                }
            }

            $this->logger->error('Product views flush failed', [
                'error' => $e->getMessage(),
                'products' => \count($counts),
                'views' => array_sum($counts),
            ]);

            throw $e;
        }

        $this->logger->info('Product views flushed', [
            'products' => \count($counts),
            'views' => array_sum($counts),
        ]);

        $io->success(\sprintf(
            'Flushed %d views for %d products.',
            array_sum($counts),
            \count($counts)
        ));

        return Command::SUCCESS;
    }
}
