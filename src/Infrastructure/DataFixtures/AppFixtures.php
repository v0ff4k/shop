<?php

declare(strict_types=1);

namespace App\Infrastructure\DataFixtures;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Основные фикстуры для приложения.
 *
 * ⚠️  НЕ создаёт пользователей!
 * Для тестовых пользователей используйте:
 *   php bin/console app:create-test-users
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Создаем категории
        $categories = $this->loadCategories($manager);

        // Создаем продукты
        $this->loadProducts($manager, $categories);

        $manager->flush();
    }

    private function loadCategories(ObjectManager $manager): array
    {
        $categories = [];

        // Электроника
        $electronics = new Category();
        $electronics->setTitle('Electronics');
        $electronics->setImage(null);
        $manager->persist($electronics);
        $categories['electronics'] = $electronics;

        $smartphones = new Category();
        $smartphones->setTitle('Smartphones');
        $smartphones->setParent($electronics);
        $manager->persist($smartphones);
        $categories['smartphones'] = $smartphones;

        $laptops = new Category();
        $laptops->setTitle('Laptops');
        $laptops->setParent($electronics);
        $manager->persist($laptops);
        $categories['laptops'] = $laptops;

        // Одежда
        $clothing = new Category();
        $clothing->setTitle('Clothing');
        $manager->persist($clothing);
        $categories['clothing'] = $clothing;

        $menClothing = new Category();
        $menClothing->setTitle('Men\'s Clothing');
        $menClothing->setParent($clothing);
        $manager->persist($menClothing);
        $categories['men_clothing'] = $menClothing;

        $womenClothing = new Category();
        $womenClothing->setTitle('Women\'s Clothing');
        $womenClothing->setParent($clothing);
        $manager->persist($womenClothing);
        $categories['women_clothing'] = $womenClothing;

        // Книги
        $books = new Category();
        $books->setTitle('Books');
        $manager->persist($books);
        $categories['books'] = $books;

        $fiction = new Category();
        $fiction->setTitle('Fiction');
        $fiction->setParent($books);
        $manager->persist($fiction);
        $categories['fiction'] = $fiction;

        $technical = new Category();
        $technical->setTitle('Technical');
        $technical->setParent($books);
        $manager->persist($technical);
        $categories['technical'] = $technical;

        return $categories;
    }

    private function loadProducts(ObjectManager $manager, array $categories): void
    {
        // Смартфоны
        $products = [
            [
                'iPhone 15 Pro',
                '999.99',
                'Latest Apple flagship smartphone with A17 Pro chip',
                $categories['smartphones'],
                50,
            ],
            [
                'Samsung Galaxy S24 Ultra',
                '899.99',
                'Premium Android smartphone with S Pen',
                $categories['smartphones'],
                30,
            ],
            [
                'Google Pixel 8 Pro',
                '799.99',
                'Google\'s flagship with advanced AI features',
                $categories['smartphones'],
                25,
            ],
            ['OnePlus 12', '699.99', 'Fast charging flagship killer', $categories['smartphones'], 40],
            ['Xiaomi 14 Pro', '599.99', 'High-end smartphone with Leica camera', $categories['smartphones'], 35],

            // Ноутбуки
            ['MacBook Pro 16"', '2499.99', 'Powerful laptop for professionals', $categories['laptops'], 15],
            ['Dell XPS 15', '1799.99', 'Premium Windows laptop', $categories['laptops'], 20],
            ['ThinkPad X1 Carbon', '1699.99', 'Business ultrabook', $categories['laptops'], 18],
            ['ASUS ROG Zephyrus G14', '1499.99', 'Gaming laptop in compact form', $categories['laptops'], 22],
            ['HP Spectre x360', '1399.99', '2-in-1 convertible laptop', $categories['laptops'], 16],

            // Мужская одежда
            ['Classic Suit', '299.99', 'Italian wool business suit', $categories['men_clothing'], 100],
            ['Denim Jacket', '79.99', 'Vintage style denim jacket', $categories['men_clothing'], 200],
            ['Cotton T-Shirt Pack', '29.99', 'Pack of 3 basic t-shirts', $categories['men_clothing'], 500],
            ['Wool Sweater', '89.99', 'Merino wool pullover', $categories['men_clothing'], 150],
            ['Chino Pants', '59.99', 'Classic fit chino pants', $categories['men_clothing'], 300],

            // Женская одежда
            ['Summer Dress', '69.99', 'Floral print sundress', $categories['women_clothing'], 250],
            ['Cashmere Scarf', '49.99', 'Luxury cashmere scarf', $categories['women_clothing'], 180],
            ['Leather Handbag', '199.99', 'Genuine leather handbag', $categories['women_clothing'], 80],
            ['Yoga Pants', '39.99', 'High-waist athletic leggings', $categories['women_clothing'], 400],
            ['Blazer', '129.99', 'Professional women\'s blazer', $categories['women_clothing'], 120],

            // Художественные книги
            ['The Great Gatsby', '8.99', 'F. Scott Fitzgerald classic', $categories['fiction'], 1000],
            ['1984', '9.99', 'George Orwell dystopia', $categories['fiction'], 800],
            ['To Kill a Mockingbird', '10.99', 'Harper Lee masterpiece', $categories['fiction'], 600],
            ['Pride and Prejudice', '7.99', 'Jane Austen romance', $categories['fiction'], 700],
            ['The Catcher in the Rye', '9.49', 'J.D. Salinger classic', $categories['fiction'], 550],

            // Технические книги
            [
                'Clean Code',
                '24.99',
                'Robert C. Martin - A Handbook of Agile Software Craftsmanship',
                $categories['technical'],
                400,
            ],
            [
                'Design Patterns',
                '29.99',
                'Gang of Four - Elements of Reusable Object-Oriented Software',
                $categories['technical'],
                350,
            ],
            ['The Pragmatic Programmer', '27.99', 'Andrew Hunt and David Thomas', $categories['technical'], 300],
            [
                'Introduction to Algorithms',
                '49.99',
                'CLRS - Comprehensive algorithms textbook',
                $categories['technical'],
                200,
            ],
            [
                'Domain-Driven Design',
                '34.99',
                'Eric Evans - Tackling Complexity in the Heart of Software',
                $categories['technical'],
                250,
            ],
        ];

        foreach ($products as $index => $productData) {
            $product = new Product(
                $productData[0],
                $productData[1],
                $productData[2],
                $productData[3]
            );
            $product->setAvailable($productData[4]);

            // Добавляем фейковые изображения для некоторых продуктов
            if ($index % 3 === 0) {
                $image = new ProductImage('placeholder_'.$index.'.jpg');
                $image->setAlt($productData[0]);
                $image->setPosition(0);
                $product->addImage($image);
            }

            // Уменьшаем viewed для имитации популярности
            $product->setViewed(random_int(10, 1000));

            $manager->persist($product);
        }
    }
}
