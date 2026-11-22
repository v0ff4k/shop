<?php

declare(strict_types=1);

namespace App\UI\Controller\Seller;

use App\Application\Command\XmlExportCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\XmlExportRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контроллер XML экспорта для продавцов.
 *
 * Позволяет продавцам экспортировать товары в XML файл.
 */
#[IsGranted('ROLE_SELLER')]
final class XmlExportController implements ControllerInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly AsyncJobTracker $jobTracker,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * POST /seller/export/xml - Экспорт в XML для продавца.
     */
    public function __invoke(XmlExportRequestDto $dto): JsonResponse
    {
        $jobId = bin2hex(random_bytes(16));
        $productIds = $dto->getProductIdsArray();
        $limit = $dto->limit;
        $outputPath = $dto->outputPath;

        $this->jobTracker->create($jobId, 'xml_export', [
            'product_ids' => $productIds,
            'limit' => $limit,
            'output_path' => $outputPath,
            'user_type' => 'seller',
        ]);

        // Get the current user ID
        $user = $this->tokenStorage->getToken()?->getUser();
        $sellerUserId = $user !== null && method_exists($user, 'getId') ? $user->getId() : 0;

        // Отправляем на асинхронную обработку
        $this->commandBus->dispatch(
            new XmlExportCommand(
                $jobId,
                $productIds,
                $limit,
                $outputPath,
                (string) $sellerUserId
            )
        );

        return new JsonResponse(
            [
                'message' => 'XML export started. You will be notified when complete.',
                'job_id' => $jobId,
                'product_ids' => $productIds,
                'limit' => $limit,
            ], 202
        );
    }
}
