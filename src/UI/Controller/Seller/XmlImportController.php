<?php

declare(strict_types=1);

namespace App\UI\Controller\Seller;

use App\Application\Command\XmlImportCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\Infrastructure\ImportExport\XmlImportStorage;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\XmlImportRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контроллер XML импорта для продавцов.
 *
 * Позволяет продавцам импортировать товары через XML файл.
 * Файл сохраняется на диск, в очередь уходит только путь к нему.
 */
#[IsGranted('ROLE_SELLER')]
final class XmlImportController implements ControllerInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly AsyncJobTracker $jobTracker,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly XmlImportStorage $importStorage,
    ) {
    }

    /**
     * POST /seller/import/xml - Импорт XML файла для продавца.
     */
    public function __invoke(XmlImportRequestDto $dto): JsonResponse
    {
        $jobId = bin2hex(random_bytes(16));
        $fileName = $dto->xmlFile->getClientOriginalName();
        $fileSize = $dto->xmlFile->getSize();
        $filePath = $this->importStorage->store($dto->xmlFile, $jobId);

        $this->jobTracker->create($jobId, 'xml_import', [
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'user_type' => 'seller',
        ]);

        $user = $this->tokenStorage->getToken()?->getUser();
        $sellerUserId = $user !== null && method_exists($user, 'getId') ? $user->getId() : 0;

        // Отправляем на асинхронную обработку
        $this->commandBus->dispatch(
            new XmlImportCommand(
                $jobId,
                $filePath,
                (string) $sellerUserId
            )
        );

        return new JsonResponse(
            [
                'message' => 'XML import started. You will be notified when complete.',
                'job_id' => $jobId,
            ], 202
        );
    }
}
