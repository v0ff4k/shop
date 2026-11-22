<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса импорта XML файла.
 */
final class XmlImportRequestDto
{
    #[Assert\NotNull(groups: ['Default'])]
    #[Assert\Type(type: UploadedFile::class, groups: ['Default'])]
    #[Assert\File(
        maxSize: '10M',
        mimeTypes: ['text/xml', 'application/xml'],
        mimeTypesMessage: 'Пожалуйста, загружите корректный XML файл',
        maxSizeMessage: 'Файл слишком большой (максимум {{ limit }} {{ suffix }})',
        groups: ['Default']
    )]
    public readonly UploadedFile $xmlFile;

    private function __construct(UploadedFile $xmlFile)
    {
        $this->xmlFile = $xmlFile;
    }

    public static function fromRequest(\Symfony\Component\HttpFoundation\Request $request): self
    {
        return self::createFromFiles($request->files->all());
    }

    public static function createFromFiles(array $files): self
    {
        if (!isset($files['xml_file']) || !$files['xml_file'] instanceof UploadedFile) {
            throw new \InvalidArgumentException('XML файл не предоставлен');
        }

        return new self($files['xml_file']);
    }
}
