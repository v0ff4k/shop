<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Infrastructure\Messaging\AsyncJobTracker;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\AsyncJobListRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Контроллер API для получения списка асинхронных задач.
 */
final class AsyncJobListController implements ControllerInterface
{
    public function __construct(
        private readonly AsyncJobTracker $jobTracker,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * GET /api/async-jobs - Список последних задач.
     */
    public function __invoke(AsyncJobListRequestDto $dto): JsonResponse
    {
        return new JsonResponse([
            'jobs' => $this->jobTracker->listRecent($dto->limit, $dto->type),
        ]);
    }
}
