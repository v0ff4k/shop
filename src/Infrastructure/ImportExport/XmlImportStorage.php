<?php

declare(strict_types=1);

namespace App\Infrastructure\ImportExport;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Хранилище загруженных XML-файлов.
 *
 * Файл сохраняется на диск сразу при загрузке (без file_get_contents),
 * а в асинхронную команду передаётся только путь. Это критично для
 * больших XML: содержимое не попадает ни в память, ни в тело сообщения.
 */
final class XmlImportStorage
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $relativeDir = 'var/uploads/xml',
    ) {
    }

    public function store(UploadedFile $file, string $jobId): string
    {
        $directory = $this->getDirectory();
        $filename = $this->filenameFor($jobId);

        $file->move($directory, $filename);

        return $directory.'/'.$filename;
    }

    public function pathFor(string $jobId): string
    {
        return $this->getDirectory().'/'.$this->filenameFor($jobId);
    }

    public function getDirectory(): string
    {
        $directory = rtrim($this->projectDir, '/').'/'.trim($this->relativeDir, '/');

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $directory));
        }

        return $directory;
    }

    private function filenameFor(string $jobId): string
    {
        $safeJobId = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId) ?? '';

        if ($safeJobId === '') {
            throw new \InvalidArgumentException('Invalid import job id');
        }

        return $safeJobId.'.xml';
    }
}
