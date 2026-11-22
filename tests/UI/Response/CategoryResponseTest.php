<?php

declare(strict_types=1);

namespace App\Tests\UI\Response;

use App\Application\DTO\Response\CategoryResponse;
use App\Domain\Shop\Entity\Category;
use PHPUnit\Framework\TestCase;

final class CategoryResponseTest extends TestCase
{
    public function testFromEntityDoesNotLoadChildrenByDefault(): void
    {
        $parent = $this->createCategory('Parent');
        $this->setId($parent, 1);
        $child = $this->createCategory('Child');
        $this->setId($child, 2);
        $parent->addChild($child);

        $response = CategoryResponse::fromEntity($parent);

        self::assertSame('Parent', $response->title);
        self::assertSame([], $response->children);
    }

    public function testFromEntityCanIncludeChildrenWhenRequested(): void
    {
        $parent = $this->createCategory('Parent');
        $this->setId($parent, 1);
        $child = $this->createCategory('Child');
        $this->setId($child, 2);
        $parent->addChild($child);

        $response = CategoryResponse::fromEntity($parent, 0, true);

        self::assertCount(1, $response->children);
        self::assertSame('Child', $response->children[0]->title);
    }

    private function createCategory(string $title): Category
    {
        $category = new Category();
        $category->setTitle($title);
        $category->setSlug(strtolower($title));

        return $category;
    }

    private function setId(Category $category, int $id): void
    {
        $reflection = new \ReflectionClass($category);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($category, $id);
    }
}
