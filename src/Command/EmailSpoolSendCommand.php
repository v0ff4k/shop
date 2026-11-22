<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Mailing\EmailSpoolService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Консольная команда для управления email spool.
 *
 * Использование:
 *   php bin/console app:email:spool:send          # Отправить все письма
 *   php bin/console app:email:spool:send --id=ID  # Отправить конкретное письмо
 *   php bin/console app:email:spool:list          # Показать все письма в spool
 *   php bin/console app:email:spool:clear         # Очистить spool
 */
#[AsCommand(
    name: 'app:email:spool:send',
    description: 'Отправить письма из spool очереди'
)]
final class EmailSpoolSendCommand extends Command
{
    public function __construct(
        private readonly EmailSpoolService $spoolService,
        private readonly MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'Действие: send, list, clear', 'send')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'ID конкретного письма для отправки')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Отправить все письма');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        return match ($action) {
            'list' => $this->listEmails($io),
            'clear' => $this->clearSpool($io),
            'send' => $this->sendEmails($input, $io),
            default => $this->sendEmails($input, $io),
        };
    }

    private function listEmails(SymfonyStyle $io): int
    {
        $emails = $this->spoolService->getSpooledEmails();
        $spoolSize = $this->spoolService->getSpoolSize();

        $io->title('Email Spool Queue');
        $io->text(\sprintf('Всего писем: <info>%d</info> | Размер: <info>%s</info>', $spoolSize['count'], $spoolSize['totalSizeHuman']));

        if (empty($emails)) {
            $io->success('Spool пуст - нет писем для отправки');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($emails as $email) {
            $rows[] = [
                $email['id'],
                $email['to'],
                $email['subject'],
                $email['date'],
                round($email['size'] / 1024, 2).' KB',
            ];
        }

        $io->table(
            ['ID', 'Кому', 'Тема', 'Дата', 'Размер'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function clearSpool(SymfonyStyle $io): int
    {
        $count = $this->spoolService->clearSpool();

        if ($count > 0) {
            $io->success(\sprintf('Удалено %d писем из spool', $count));
        } else {
            $io->info('Spool уже пуст');
        }

        return Command::SUCCESS;
    }

    private function sendEmails(InputInterface $input, SymfonyStyle $io): int
    {
        $emailId = $input->getOption('id');
        $sendAll = $input->getOption('all') || $emailId === null;

        if ($emailId !== null) {
            // Отправляем конкретное письмо
            $io->title('Отправка конкретного письма');

            $success = $this->spoolService->sendSpooledEmail(
                $emailId,
                fn ($email) => $this->mailer->send($email)
            );

            if ($success) {
                $io->success(\sprintf('Письмо "%s" успешно отправлено', $emailId));
            } else {
                $io->error(\sprintf('Не удалось отправить письмо "%s"', $emailId));

                return Command::FAILURE;
            }
        } elseif ($sendAll) {
            // Отправляем все письма
            $emails = $this->spoolService->getSpooledEmails();

            if (empty($emails)) {
                $io->info('Spool пуст - нет писем для отправки');

                return Command::SUCCESS;
            }

            $io->title(\sprintf('Отправка всех писем (%d шт.)', \count($emails)));

            $progressBar = $io->createProgressBar(\count($emails));
            $progressBar->start();

            $result = $this->spoolService->sendAllSpooledEmails(
                fn ($email) => $this->mailer->send($email)
            );

            $progressBar->finish();
            $io->newLine(2);

            if ($result['sent'] > 0) {
                $io->success(\sprintf('Отправлено %d писем', $result['sent']));
            }

            if ($result['failed'] > 0) {
                $io->warning(\sprintf('Не удалось отправить %d писем', $result['failed']));

                if (!empty($result['errors'])) {
                    $io->listing($result['errors']);
                }
            }
        }

        return Command::SUCCESS;
    }
}
