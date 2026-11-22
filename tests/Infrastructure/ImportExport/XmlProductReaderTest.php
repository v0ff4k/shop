<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImportExport;

use App\Infrastructure\ImportExport\XmlProductReader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\ImportExport\XmlProductReader
 */
final class XmlProductReaderTest extends TestCase
{
    private XmlProductReader $reader;

    protected function setUp(): void
    {
        $this->reader = new XmlProductReader();
    }

    public function testReadsProductsFromString(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <slug>one</slug>
        <title>One</title>
        <price>10.00</price>
    </product>
    <product>
        <slug>two</slug>
        <title>Two</title>
        <price>20.00</price>
    </product>
</products>
XML;

        $products = iterator_to_array($this->reader->readFromString($xml));

        $this->assertCount(2, $products);
        $this->assertSame('one', $products[0]['slug']);
        $this->assertSame('Two', $products[1]['title']);
    }

    public function testReadsNestedCategoryAndImages(): void
    {
        $xml = <<<XML
<products>
    <product>
        <slug>nested</slug>
        <title>Nested</title>
        <images>
            <image>https://example.com/1.jpg</image>
            <image>https://example.com/2.jpg</image>
        </images>
        <category>
            <slug>nested-category</slug>
            <title>Nested Category</title>
        </category>
    </product>
</products>
XML;

        $products = iterator_to_array($this->reader->readFromString($xml));

        $this->assertSame(['https://example.com/1.jpg', 'https://example.com/2.jpg'], $products[0]['images']);
        $this->assertSame(
            ['slug' => 'nested-category', 'title' => 'Nested Category'],
            $products[0]['category']
        );
    }

    public function testReadsScalarCategory(): void
    {
        $xml = '<products><product><slug>p</slug><title>P</title><category>Electronics</category></product></products>';

        $products = iterator_to_array($this->reader->readFromString($xml));

        $this->assertSame('Electronics', $products[0]['category']);
    }

    public function testThrowsWhenNoProductsFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('products not found');

        iterator_to_array($this->reader->readFromString('<root><invalid>data</invalid></root>'));
    }

    public function testThrowsOnMalformedXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid XML content');

        iterator_to_array($this->reader->readFromString('<products><product><slug>broken'));
    }

    public function testThrowsOnEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid XML content');

        iterator_to_array($this->reader->readFromString('   '));
    }
}
