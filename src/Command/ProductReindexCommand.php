<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Search\ProductIndexer;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:product:reindex', description: 'Переиндексация всех товаров в Elasticsearch')]
final class ProductReindexCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly ProductIndexer $productIndexer,
        private readonly Connection $dbConnection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Переиндексация товаров в Elasticsearch');

        // Получаем общее количество товаров
        $total = (int) $this->dbConnection->executeQuery('SELECT COUNT(*) FROM product')->fetchOne();

        $io->text("Найдено товаров: $total");

        if ($total === 0) {
            $io->warning('Нет товаров для индексации');

            return Command::SUCCESS;
        }

        $io->text('Индексация...');

        $processed = 0;
        $offset = 0;

        try {
            while ($processed < $total) {
                // Получаем партию товаров
                $products = $this->dbConnection->fetchAllAssociative(
                    'SELECT id, title, description, slug, price, category_id, available, added, viewed FROM product LIMIT :limit OFFSET :offset',
                    [
                        'limit' => self::BATCH_SIZE,
                        'offset' => $offset,
                    ]
                );

                if (empty($products)) {
                    break;
                }

                // Индексируем партию
                $this->productIndexer->indexProductsBatch($products);

                $processed += \count($products);
                $offset += self::BATCH_SIZE;

                // Обновляем прогресс
                $io->text(\sprintf('Проиндексировано %d из %d товаров...', $processed, $total));
            }

            $io->success("Проиндексировано $processed товаров");
        } catch (\Throwable $e) {
            $io->error('Ошибка индексации: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
