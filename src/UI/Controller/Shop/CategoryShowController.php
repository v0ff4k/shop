<?php

declare(strict_types=1);

namespace App\UI\Controller\Shop;

use App\Application\Query\ProductQueryService;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\UI\Controller\ControllerInterface;
use App\UI\Factory\ResponseFactory;
use App\UI\Request\CategoryViewRequestDto;
use App\UI\Response\ViewResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Контроллер просмотра категории.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CategoryShowController implements ControllerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductQueryService $productQueryService
    ) {
    }

    /**
     * GET /category/{slug} - Просмотр категории.
     *
     * DTO создаётся и валидируется автоматически через RequestDtoResolver
     */
    public function __invoke(CategoryViewRequestDto $dto): ViewResponse
    {
        $category = $this->categoryRepository->findBySlug($dto->slug); // TODO add cache !

        if ($category === null) {
            throw new NotFoundHttpException('Category not found');
        }

        $catId = $category->getId();

        $products = $this->productQueryService->findByCategory($catId, $dto->limit, $dto->getOffset());
        // $total = $this->productQueryService->countByCategory($category->getId());
        $total = \count($products);
        $subcategories = $this->categoryRepository->findSubcategories($catId); // TODO add cache !

        /* dd($products, ResponseFactory::productsFromEntities($products)); */

        return new ViewResponse(
            'shop/category/show.html.twig', [
                'category' => ResponseFactory::categoryFromEntity($category, $total),
                'products' => ResponseFactory::paginatedResponse(
                    ResponseFactory::productsFromEntities($products),
                    $total,
                    $dto->page,
                    $dto->limit
                ),
                'subcategories' => ResponseFactory::categoriesFromEntities($subcategories),
            ]
        );
    }
}
