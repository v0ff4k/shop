<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Infrastructure\Messaging\AsyncJobTracker;
use App\UI\Controller\ControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AsyncJobStatusController implements ControllerInterface
{
    public function __construct(
        private readonly AsyncJobTracker $jobTracker
    ) {
    }

    public function __invoke(string $jobId): JsonResponse
    {
        $job = $this->jobTracker->get($jobId);
        if ($job === null) {
            return new JsonResponse(['error' => 'Job not found'], 404);
        }

        return new JsonResponse(['job' => $job], 200);
    }
}
