<?php

declare(strict_types=1);

namespace App\Infrastructure\ImportExport;

use XMLReader;

/**
 * Потоковый читатель товаров из XML.
 *
 * Использует XMLReader (SAX-подобный pull-парсер), поэтому в памяти
 * находится только один товар за раз, а не весь документ.
 * Это позволяет импортировать XML произвольного размера без риска
 * исчерпать memory_limit и без загрузки файла целиком в строку.
 *
 * Поддерживаемые структуры:
 *
 * <products>
 *     <product>
 *         <slug>...</slug>
 *         <title>...</title>
 *         <price>...</price>
 *         <images>
 *             <image>https://example.com/1.jpg</image>
 *         </images>
 *         <category>
 *             <slug>...</slug>
 *             <title>...</title>
 *         </category>
 *     </product>
 * </products>
 *
 * Категория также может быть простой строкой: <category>Electronics</category>
 */
final class XmlProductReader
{
    private const LIBXML_FLAGS = \LIBXML_NONET | \LIBXML_COMPACT;

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function readFromFile(string $filePath): \Generator
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException(\sprintf('XML file is not readable: %s', $filePath));
        }

        $reader = new \XMLReader();
        if (!$reader->open($filePath, 'UTF-8', self::LIBXML_FLAGS)) {
            throw new \InvalidArgumentException(\sprintf('Unable to open XML file: %s', $filePath));
        }

        try {
            yield from $this->read($reader);
        } finally {
            $reader->close();
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function readFromString(string $xmlContent): \Generator
    {
        if (trim($xmlContent) === '') {
            throw new \InvalidArgumentException('Invalid XML content');
        }

        $reader = new \XMLReader();
        if (!$reader->XML($xmlContent, 'UTF-8', self::LIBXML_FLAGS)) {
            throw new \InvalidArgumentException('Invalid XML content');
        }

        try {
            yield from $this->read($reader);
        } finally {
            $reader->close();
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function read(\XMLReader $reader): \Generator
    {
        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $found = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'product') {
                    continue;
                }

                ++$found;

                yield $this->readProduct($reader);
            }

            $error = $this->firstParseError();
            if ($error !== null) {
                throw new \InvalidArgumentException('Invalid XML content: '.$error);
            }

            if ($found === 0) {
                throw new \InvalidArgumentException('Invalid XML format: products not found');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readProduct(\XMLReader $reader): array
    {
        $product = [];

        if ($reader->isEmptyElement) {
            return $product;
        }

        $depth = $reader->depth;

        while ($reader->read()) {
            if (
                $reader->nodeType === \XMLReader::END_ELEMENT
                && $reader->depth === $depth
                && $reader->name === 'product'
            ) {
                break;
            }

            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->depth !== $depth + 1) {
                continue;
            }

            $product[$reader->name] = match ($reader->name) {
                'images' => $this->readImages($reader),
                'category' => $this->readCategory($reader),
                default => $this->readElementValue($reader),
            };
        }

        return $product;
    }

    /**
     * Читает текстовое значение простого элемента.
     */
    private function readElementValue(\XMLReader $reader): string
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $depth = $reader->depth;
        $value = '';

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
                $value .= $reader->value;
            }
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function readImages(\XMLReader $reader): array
    {
        $images = [];

        if ($reader->isEmptyElement) {
            return $images;
        }

        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->depth === $depth + 1) {
                $url = $this->readElementValue($reader);
                if ($url !== '') {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    /**
     * Категория может быть как строкой, так и вложенной структурой.
     *
     * @return string|array<string, string>
     */
    private function readCategory(\XMLReader $reader): string|array
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $depth = $reader->depth;
        $text = '';
        $children = [];

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->depth === $depth + 1) {
                $children[$reader->name] = $this->readElementValue($reader);
                continue;
            }

            if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
                $text .= $reader->value;
            }
        }

        return $children !== [] ? $children : trim($text);
    }

    private function firstParseError(): ?string
    {
        foreach (libxml_get_errors() as $error) {
            if ($error->level >= \LIBXML_ERR_ERROR) {
                return trim($error->message);
            }
        }

        return null;
    }
}
