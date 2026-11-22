<?php

declare(strict_types=1);

namespace App\UI\Controller\Admin;

use App\Application\Command\XmlImportCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\Infrastructure\ImportExport\XmlImportStorage;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\UI\Controller\ControllerInterface;
use App\UI\Request\AdminXmlImportRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контроллер XML импорта.
 *
 * Принимает XML файл, сохраняет его на диск и отправляет
 * на асинхронную обработку только путь к файлу.
 */
#[IsGranted('ROLE_ADMIN')]
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
     * POST /admin/import/xml - Импорт XML файла.
     */
    public function __invoke(AdminXmlImportRequestDto $dto): JsonResponse
    {
        $jobId = bin2hex(random_bytes(16));
        $fileName = $dto->xmlFile->getClientOriginalName();
        $fileSize = $dto->xmlFile->getSize();
        $filePath = $this->importStorage->store($dto->xmlFile, $jobId);

        $this->jobTracker->create($jobId, 'xml_import', [
            'file_name' => $fileName,
            'file_size' => $fileSize,
        ]);

        $userId = $dto->userId;
        if ($userId === null) {
            $user = $this->tokenStorage->getToken()?->getUser();
            $userId = $user !== null && method_exists($user, 'getId') ? $user->getId() : 1;
        }

        // Отправляем на асинхронную обработку
        $this->commandBus->dispatch(
            new XmlImportCommand(
                $jobId,
                $filePath,
                (string) $userId
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
