<?php

declare(strict_types=1);

namespace App\UI\Controller\Admin;

use App\Application\Command\XmlExportCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\UI\Controller\ControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AsyncJobRetryController implements ControllerInterface
{
    public function __construct(
        private readonly AsyncJobTracker $jobTracker,
        private readonly CommandBusInterface $commandBus
    ) {
    }

    public function __invoke(string $jobId): JsonResponse
    {
        $job = $this->jobTracker->get($jobId);
        if ($job === null) {
            return new JsonResponse(['error' => 'Job not found'], 404);
        }

        if (($job['status'] ?? null) !== 'failed') {
            return new JsonResponse(['error' => 'Only failed jobs can be retried'], 400);
        }

        if (($job['type'] ?? null) !== 'xml_export') {
            return new JsonResponse(['error' => 'Retry is currently supported only for xml_export jobs'], 400);
        }

        $payload = \is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $newJobId = bin2hex(random_bytes(16));

        $this->jobTracker->create($newJobId, 'xml_export', $payload);
        $this->commandBus->dispatch(
            new XmlExportCommand(
                $newJobId,
                array_map('intval', (array) ($payload['product_ids'] ?? [])),
                (int) ($payload['limit'] ?? 1000),
                isset($payload['output_path']) && \is_string($payload['output_path']) ? $payload['output_path'] : null,
                (string) (isset($payload['export_user_id']) ? $payload['export_user_id'] : 1)
            )
        );

        return new JsonResponse([
            'message' => 'Retry started',
            'job_id' => $newJobId,
        ], 202);
    }
}
