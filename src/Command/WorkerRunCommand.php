<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Консольная команда для запуска worker процесса
 * Обрабатывает сообщения из очередей.
 */
#[AsCommand(
    name: 'app:worker:run',
    description: 'Запуск worker процесса для обработки очередей сообщений',
    hidden: false
)]
final class WorkerRunCommand extends Command
{
    private const MEMORY_LIMIT_MB = 128;
    private const MAX_MESSAGES = 1000;
    private const SLEEP_US = 500000; // 500ms

    public function __construct(
        private readonly ReceiverInterface $receiver
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Лимит памяти в MB', self::MEMORY_LIMIT_MB)
            ->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Максимум сообщений за цикл', self::MAX_MESSAGES)
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Пауза между циклами в микросекундах', self::SLEEP_US);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Worker Process');

        $memoryLimit = (int) $input->getOption('memory-limit') * 1024 * 1024;
        $maxMessages = (int) $input->getOption('max-messages');
        $sleepUs = (int) $input->getOption('sleep');

        $io->text(
            [
                "Memory Limit: {$memoryLimit} bytes",
                "Max Messages: {$maxMessages}",
                "Sleep: {$sleepUs} μs",
                '',
                'Press Ctrl+C to stop',
            ]
        );

        $processedCount = 0;
        $startTime = time();

        while (true) {
            // Проверка лимита памяти
            $memoryUsage = memory_get_usage(true);
            if ($memoryUsage > $memoryLimit) {
                $io->warning('Memory limit reached: '.round($memoryUsage / 1024 / 1024, 2).' MB');
                $io->note('Restarting worker...');

                return Command::SUCCESS;
            }

            // Проверка лимита сообщений
            if ($processedCount >= $maxMessages) {
                $io->success("Processed {$processedCount} messages");

                return Command::SUCCESS;
            }

            // Получение сообщений из очереди
            $envelopes = $this->receiver->get();

            if (\count($envelopes) === 0) {
                usleep($sleepUs);
                continue;
            }

            foreach ($envelopes as $envelope) {
                if ($envelope === null) {
                    continue;
                }

                try {
                    $this->receiver->ack($envelope);
                    ++$processedCount;

                    if ($output->isVerbose()) {
                        $io->text("✓ Processed message #{$processedCount}");
                    }
                } catch (\Throwable $e) {
                    $io->error("Error processing message: {$e->getMessage()}");

                    // Отправляем сообщение обратно в очередь
                    $this->receiver->reject($envelope);
                }
            }

            // Логирование прогресса каждые 100 сообщений
            if ($processedCount % 100 === 0 && $processedCount > 0) {
                $elapsed = time() - $startTime;
                $rate = $elapsed > 0 ? round($processedCount / $elapsed, 2) : 0;

                $io->text(
                    \sprintf(
                        'Progress: %d messages, %d sec elapsed, %.2f msg/sec',
                        $processedCount,
                        $elapsed,
                        $rate
                    )
                );
            }
        }
    }
}
