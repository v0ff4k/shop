<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop\Entity;

use App\Domain\Shop\Entity\Category;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для доменной сущности Category.
 */
final class CategoryTest extends TestCase
{
    public function testCreateCategory(): void
    {
        $category = new Category('Test Category');
        $category->setImage('category.jpg');

        $this->assertSame('Test Category', $category->getTitle());
        $this->assertSame('test-category', $category->getSlug());
        $this->assertSame('category.jpg', $category->getImage());
        $this->assertNull($category->getParent());
        $this->assertCount(0, $category->getChildren());
        $this->assertNotNull($category->getAdded());
    }

    public function testAddChildCategory(): void
    {
        $parent = new Category('Parent Category');
        $child = new Category('Child Category');
        // Simulate a persisted child: without an id the entity-level cycle
        // guard compares null === null and rejects every transient category.
        $this->assignId($child, 2);

        $parent->addChild($child);

        $this->assertCount(1, $parent->getChildren());
        $this->assertSame($parent, $child->getParent());
        $this->assertSame('Parent Category / Child Category', $child->getPath());
    }

    public function testCategorySlugGeneration(): void
    {
        $category = new Category();
        $category->genFromTitle('My Awesome Category');

        $this->assertSame('my-awesome-category', $category->getSlug());
    }

    public function testUpdateCategory(): void
    {
        $category = new Category('Old Title');
        $category->setImage('old.jpg');

        $category->setTitle('New Title');
        $category->setSlug('new-title');
        $category->setImage('new.jpg');
        $category->onPreUpdate();

        $this->assertSame('New Title', $category->getTitle());
        $this->assertSame('new-title', $category->getSlug());
        $this->assertSame('new.jpg', $category->getImage());
        $this->assertNotNull($category->getEdited());
    }

    private function assignId(Category $category, int $id): void
    {
        (new \ReflectionProperty(Category::class, 'id'))->setValue($category, $id);
    }
}
