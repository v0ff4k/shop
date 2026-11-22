<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use Symfony\Component\Mime\Email;

/**
 * Сервис для работы с email spool.
 *
 * В dev режиме сохраняет письма в var/spool/email/
 * В prod режиме отправляет сразу через MailerInterface
 */
final class EmailSpoolService
{
    private string $spoolDir;

    public function __construct(
        private readonly string $projectDir,
        private readonly bool $spoolEnabled = false
    ) {
        $this->spoolDir = $projectDir.'/var/spool/email';
    }

    /**
     * Проверить включён ли spool режим
     */
    public function isSpoolEnabled(): bool
    {
        return $this->spoolEnabled;
    }

    /**
     * Сохранить письмо в spool.
     */
    public function spool(Email $email): string
    {
        if (!is_dir($this->spoolDir)) {
            if (!mkdir($concurrentDirectory = $this->spoolDir, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        // Генерируем уникальное имя файла
        $filename = date('Y-m-d_H-i-s').'_'.uniqid().'.eml';
        $filepath = $this->spoolDir.'/'.$filename;

        // Сериализуем email в файл
        $serialized = serialize($email);
        file_put_contents($filepath, $serialized);

        return $filename;
    }

    /**
     * Получить все письма в spool.
     *
     * @return array<array{id: string, filename: string, to: string, subject: string, date: string, size: int}>
     */
    public function getSpooledEmails(): array
    {
        if (!is_dir($this->spoolDir)) {
            return [];
        }

        $files = glob($this->spoolDir.'/*.eml');
        if ($files === false) {
            return [];
        }

        // Сортируем по дате создания (новые первые)
        usort($files, static fn ($a, $b) => filemtime($b) - filemtime($a));

        $emails = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            try {
                $email = unserialize($content, ['allowed_classes' => true, 'max_depth' => 10]);

                if ($email instanceof Email) {
                    $emails[] = [
                        'id' => basename($file, '.eml'),
                        'filename' => basename($file),
                        'to' => implode(', ', array_map(static fn ($a) => $a->getAddress(), $email->getTo())),
                        'subject' => $email->getSubject() ?? '(без темы)',
                        'date' => date('Y-m-d H:i:s', filemtime($file)),
                        'size' => \strlen($content),
                    ];
                }
            } catch (\Throwable) {
                // Пропускаем битые файлы
                continue;
            }
        }

        return $emails;
    }

    /**
     * Отправить конкретное письмо из spool.
     */
    public function sendSpooledEmail(string $filename, callable $sender): bool
    {
        $filepath = $this->spoolDir.'/'.$filename;

        if (!file_exists($filepath)) {
            return false;
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            return false;
        }

        try {
            $email = unserialize($content, ['allowed_classes' => true, 'max_depth' => 10]);

            if ($email instanceof Email) {
                $sender($email);
                unlink($filepath);

                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Отправить все письма из spool.
     *
     * @return array{sent: int, failed: int, errors: array}
     */
    public function sendAllSpooledEmails(callable $sender): array
    {
        $emails = $this->getSpooledEmails();
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($emails as $emailData) {
            try {
                $success = $this->sendSpooledEmail($emailData['filename'], $sender);

                if ($success) {
                    ++$sent;
                } else {
                    ++$failed;
                    $errors[] = $emailData['filename'];
                }
            } catch (\Throwable $e) {
                ++$failed;
                $errors[] = $emailData['filename'].': '.$e->getMessage();
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Удалить конкретное письмо из spool.
     */
    public function deleteSpooledEmail(string $filename): bool
    {
        $filepath = $this->spoolDir.'/'.$filename;

        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return false;
    }

    /**
     * Очистить все письма из spool.
     */
    public function clearSpool(): int
    {
        if (!is_dir($this->spoolDir)) {
            return 0;
        }

        $files = glob($this->spoolDir.'/*.eml');
        if ($files === false) {
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Получить размер spool.
     */
    public function getSpoolSize(): array
    {
        $emails = $this->getSpooledEmails();
        $totalSize = array_sum(array_map(static fn ($e) => $e['size'], $emails));

        return [
            'count' => \count($emails),
            'totalSize' => $totalSize,
            'totalSizeHuman' => $this->formatBytes($totalSize),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
