<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\UI\Controller\ControllerInterface;
use App\UI\Factory\ResponseFactory;
use App\UI\Response\ViewResponse;

/**
 * Контроллер списка категорий.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CategoryIndexController implements ControllerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * GET /category - Список категорий.
     */
    public function __invoke(): ViewResponse
    {
        $categories = $this->categoryRepository->findAll();

        return new ViewResponse(
            'shop/category/index.html.twig', [
                'categories' => ResponseFactory::categoriesFromEntities($categories),
            ]
        );
    }
}
