<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SendEmailCommand;
use App\Infrastructure\Mailing\EmailSpoolService;
use App\Infrastructure\Redis\RedisLock;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Обработчик команды отправки email.
 *
 * Поддерживает два режима:
 * - Spool (dev): сохраняет письма в var/spool/email/
 * - Direct (prod): отправляет сразу через MailerInterface
 */
final class SendEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly EmailSpoolService $spoolService,
        private readonly ?RedisLock $lock = null
    ) {
    }

    public function __invoke(SendEmailCommand $command): void
    {
        $startedAt = microtime(true);
        $lockKey = 'email:send:'.sha1($command->to.'|'.$command->subject);
        $lockToken = $this->lock?->acquire($lockKey, 60);

        if ($this->lock !== null && $lockToken === null) {
            $this->logger->warning('Email send skipped: duplicate in progress', [
                'to' => $command->to,
                'subject' => $command->subject,
            ]);

            return;
        }

        $this->logger->info(
            'Sending email',
            [
                'to' => $command->to,
                'subject' => $command->subject,
                'mode' => $this->spoolService->isSpoolEnabled() ? 'spool' : 'direct',
            ]
        );

        try {
            // Создаем email
            $email = $this->createEmail($command);

            // Отправляем или сохраняем в spool
            if ($this->spoolService->isSpoolEnabled()) {
                $filename = $this->spoolService->spool($email);

                $this->logger->info(
                    'Email spooled successfully',
                    [
                        'to' => $command->to,
                        'subject' => $command->subject,
                        'filename' => $filename,
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    ]
                );
            } else {
                // Отправляем сразу
                $this->mailer->send($email);

                $this->logger->info(
                    'Email sent successfully',
                    [
                        'to' => $command->to,
                        'subject' => $command->subject,
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send email',
                [
                    'to' => $command->to,
                    'subject' => $command->subject,
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]
            );

            throw $e;
        } finally {
            if ($lockToken !== null) {
                $this->lock?->release($lockKey, $lockToken);
            }
        }
    }

    private function createEmail(SendEmailCommand $command): Email
    {
        // Если есть HTML тело - используем TemplatedEmail
        if ($command->htmlBody !== null) {
            $templatedEmail = new TemplatedEmail();

            $templatedEmail
                ->to($command->to)
                ->subject($command->subject)
                ->html($command->htmlBody);

            // Добавляем контекст для Twig
            if ($command->context !== null) {
                $templatedEmail->context($command->context);
            }

            return $templatedEmail;
        }

        // Простой текстовый email
        $email = new Email();
        $email
            ->to($command->to)
            ->subject($command->subject)
            ->text($command->body);

        return $email;
    }
}
