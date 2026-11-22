<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Query\ProductQueryService;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\UI\Controller\ControllerInterface;
use App\UI\Factory\ResponseFactory;
use App\UI\Response\ViewResponse;
use Twig\Environment;

/**
 * Контроллер главной страницы магазина.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class HomeController implements ControllerInterface
{
    public function __construct(
        private readonly ProductQueryService $productQueryService,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly Environment $twig
    ) {
    }

    /**
     * GET / - Главная страница.
     */
    public function __invoke(): ViewResponse
    {
        // Получаем данные через Query сервисы
        $newProducts = $this->productQueryService->findNew(8);
        $popularProducts = $this->productQueryService->findPopular(8);
        $categories = $this->categoryRepository->findRootCategories();

        // Возвращаем ViewResponse с данными
        // ResponseFactory маппирует Entity → Response DTO
        return new ViewResponse(
            'shop/home/index.html.twig', [
                'newProducts' => ResponseFactory::productsFromEntities($newProducts),
                'popularProducts' => ResponseFactory::productsFromEntities($popularProducts),
                'categories' => ResponseFactory::categoriesFromEntities($categories),
            ]
        );
    }
}
