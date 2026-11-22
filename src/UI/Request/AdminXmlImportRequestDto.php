<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса импорта XML файла (админ панель).
 */
final class AdminXmlImportRequestDto
{
    #[Assert\NotNull(groups: ['Default'])]
    #[Assert\Type(type: UploadedFile::class, groups: ['Default'])]
    #[Assert\File(
        maxSize: '200M',
        mimeTypes: ['text/xml', 'application/xml'],
        mimeTypesMessage: 'Пожалуйста, загружите корректный XML файл',
        maxSizeMessage: 'Файл слишком большой (максимум {{ limit }} {{ suffix }})',
        groups: ['Default']
    )]
    public readonly UploadedFile $xmlFile;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 999999999999999)]
    public readonly ?int $userId;

    private function __construct(
        UploadedFile $xmlFile,
        ?int $userId = null,
    ) {
        $this->xmlFile = $xmlFile;
        $this->userId = $userId;
    }

    public static function fromRequest(\Symfony\Component\HttpFoundation\Request $request): self
    {
        $userId = $request->request->getInt('user_id');

        return self::createFromFiles($request->files->all(), $userId > 0 ? $userId : null);
    }

    public static function createFromFiles(array $files, ?int $userId = null): self
    {
        if (!isset($files['xml_file']) || !$files['xml_file'] instanceof UploadedFile) {
            throw new \InvalidArgumentException('XML файл не предоставлен');
        }

        return new self($files['xml_file'], $userId);
    }
}
