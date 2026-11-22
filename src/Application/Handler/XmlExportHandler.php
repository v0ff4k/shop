<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\XmlExportCommand;
use App\Application\Service\EmailNotificationService;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\ImportExport\XmlImportExportService;
use App\Infrastructure\Messaging\AsyncJobTracker;
use Psr\Log\LoggerInterface;

/**
 * Обработчик команды асинхронного экспорта XML.
 */
final class XmlExportHandler
{
    public function __construct(
        private readonly XmlImportExportService $xmlImportExportService,
        private readonly AsyncJobTracker $jobTracker,
        private readonly LoggerInterface $logger,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailNotificationService $emailNotificationService,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(XmlExportCommand $command): string
    {
        $startedAt = microtime(true);
        $this->jobTracker->markRunning($command->jobId);

        $this->logger->info(
            'Starting XML export',
            [
                'job_id' => $command->jobId,
                'product_ids' => $command->productIds,
                'limit' => $command->limit,
            ]
        );

        try {
            $xml = $this->xmlImportExportService->exportProducts(
                $command->productIds,
                $command->limit
            );

            // Save the XML to a file for artifact (in exports/seller directory)
            $exportsDir = rtrim($this->projectDir, '/').'/var/exports/seller';
            if (!is_dir($exportsDir)) {
                if (!mkdir($exportsDir, 0755, true) && !is_dir($exportsDir)) {
                    throw new \RuntimeException(\sprintf('Directory "%s" was not created', $exportsDir));
                }
            }
            $xmlFilePath = $exportsDir.'/'.$command->jobId.'.xml';
            if (file_put_contents($xmlFilePath, $xml) === false) {
                $this->logger->warning('Failed to save XML file for artifact', [
                    'job_id' => $command->jobId,
                    'path' => $xmlFilePath,
                ]);
            } else {
                $this->logger->info('XML file saved for artifact', [
                    'job_id' => $command->jobId,
                    'path' => $xmlFilePath,
                ]);
            }

            // Если указан путь для сохранения (для обратной совместимости)
            if ($command->outputPath !== null) {
                $filename = basename($command->outputPath);
                $this->xmlImportExportService->saveToFile($xml, $filename);
            }

            $this->logger->info(
                'XML export completed',
                [
                    'job_id' => $command->jobId,
                    'xml_length' => \strlen($xml),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]
            );

            // Update job result with artifact path
            $result = [
                'xml_length' => \strlen($xml),
                'output_path' => $command->outputPath,
                'products_count' => $this->xmlImportExportService->getLastExportCount(),
                'xml_file_path' => $xmlFilePath,
            ];
            $this->jobTracker->markCompleted($command->jobId, $result);

            // Send notification email to the seller
            try {
                $userId = (int) $command->sellerUserId;
                $user = $userId > 0
                    ? $this->userRepository->findById(\App\Domain\User\ValueObject\UserId::fromInt($userId))
                    : null;
                if ($user !== null) {
                    $this->emailNotificationService->sendEmail(
                        to: $user->getEmail(),
                        subject: 'XML экспорт завершен',
                        template: 'emails/xml_export_result.html.twig',
                        context: [
                            'username' => $user->getUsername(),
                            'jobId' => $command->jobId,
                            'xmlLength' => \strlen($xml),
                            'productsCount' => $this->xmlImportExportService->getLastExportCount(),
                            'filePath' => $xmlFilePath,
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to send XML export notification email', [
                    'job_id' => $command->jobId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $xml;
        } catch (\Exception $e) {
            $this->logger->error(
                'XML export failed',
                [
                    'job_id' => $command->jobId,
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]
            );
            $this->jobTracker->markFailed($command->jobId, $e->getMessage());

            // Send notification email about failure
            try {
                $userId = (int) $command->sellerUserId;
                $user = $userId > 0
                    ? $this->userRepository->findById(\App\Domain\User\ValueObject\UserId::fromInt($userId))
                    : null;
                if ($user !== null) {
                    $this->emailNotificationService->sendEmail(
                        to: $user->getEmail(),
                        subject: 'Ошибка XML экспорта',
                        template: 'emails/xml_export_error.html.twig',
                        context: [
                            'username' => $user->getUsername(),
                            'jobId' => $command->jobId,
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to send XML export error notification email', [
                    'job_id' => $command->jobId,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
