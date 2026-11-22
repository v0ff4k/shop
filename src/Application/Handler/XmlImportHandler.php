<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\XmlImportCommand;
use App\Application\Service\EmailNotificationService;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\ImportExport\XmlImportExportService;
use App\Infrastructure\ImportExport\XmlImportStorage;
use App\Infrastructure\Messaging\AsyncJobTracker;
use Psr\Log\LoggerInterface;

/**
 * Обработчик команды асинхронного импорта XML.
 *
 * Работает с файлом на диске: XMLReader читает его потоково,
 * поэтому размер файла не влияет на потребление памяти.
 */
final class XmlImportHandler
{
    public function __construct(
        private readonly XmlImportExportService $xmlImportExportService,
        private readonly AsyncJobTracker $jobTracker,
        private readonly LoggerInterface $logger,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailNotificationService $emailNotificationService,
        private readonly XmlImportStorage $importStorage,
    ) {
    }

    public function __invoke(XmlImportCommand $command): array
    {
        $startedAt = microtime(true);
        $this->jobTracker->markRunning($command->jobId);

        try {
            $filePath = $this->resolveFilePath($command);

            $this->logger->info(
                'Starting XML import',
                [
                    'job_id' => $command->jobId,
                    'file_path' => $filePath,
                    'file_size' => filesize($filePath),
                ]
            );

            $stats = $this->xmlImportExportService->importProductsFromFile($filePath);

            $this->logger->info('XML import completed', array_merge($stats, [
                'job_id' => $command->jobId,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]));

            // Update job result with artifact path
            $result = array_merge($stats, [
                'xml_file_path' => $filePath,
            ]);
            $this->jobTracker->markCompleted($command->jobId, $result);

            $this->notifySeller(
                $command,
                'XML импорт завершен',
                'emails/xml_import_result.html.twig',
                [
                    'jobId' => $command->jobId,
                    'imported' => $stats['imported'],
                    'skipped' => $stats['skipped'],
                    'errors' => $stats['errors'],
                    'filePath' => $filePath,
                ]
            );

            return $stats;
        } catch (\Exception $e) {
            $this->logger->error(
                'XML import failed',
                [
                    'job_id' => $command->jobId,
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]
            );
            $this->jobTracker->markFailed($command->jobId, $e->getMessage());

            $this->notifySeller(
                $command,
                'Ошибка XML импорта',
                'emails/xml_import_error.html.twig',
                [
                    'jobId' => $command->jobId,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Возвращает путь к XML-файлу для потокового импорта.
     *
     * Новые команды всегда содержат путь к уже сохранённому файлу.
     * Для обратной совместимости поддерживается команда с содержимым XML.
     */
    private function resolveFilePath(XmlImportCommand $command): string
    {
        if ($command->filePath !== null && is_file($command->filePath) && is_readable($command->filePath)) {
            return $command->filePath;
        }

        if ($command->xmlContent === null || trim($command->xmlContent) === '') {
            throw new \InvalidArgumentException(\sprintf('XML import file not found: %s', $command->filePath ?? '(path is not provided)'));
        }

        $filePath = $this->importStorage->pathFor($command->jobId);
        if (file_put_contents($filePath, $command->xmlContent) === false) {
            throw new \RuntimeException('Failed to save XML file for import');
        }

        return $filePath;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function notifySeller(
        XmlImportCommand $command,
        string $subject,
        string $template,
        array $context,
    ): void {
        $userId = (int) $command->sellerUserId;
        if ($userId <= 0) {
            return;
        }

        try {
            $user = $this->userRepository->findById(UserId::fromInt($userId));
            if ($user === null) {
                return;
            }

            $this->emailNotificationService->sendEmail(
                to: $user->getEmail(),
                subject: $subject,
                template: $template,
                context: array_merge(
                    [
                        'username' => $user->getUsername(),
                    ],
                    $context
                )
            );
        } catch (\Exception $e) {
            $this->logger->warning('Failed to send XML import notification email', [
                'job_id' => $command->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
