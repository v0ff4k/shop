<?php

namespace ShopBundle\Controller;

use App\ShopBundle\Controller\MainController;
use ShopBundle\Entity\Product;
use ShopBundle\Entity\Category;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of StoreController
 */
class CategoryController extends MainController
{

    /**
     * Show category with products by specific id or title
     */
    public function showAction(Category $category, $page, $orderByColumn, $orderByDirection)
    {
        $limit = $this->container->getParameter('product_perpage_limit');

        // set offset=0, if page =< 1, else default $offset
        $offset = ($page <= 1) ? 0 : (($page - 1) * $limit);

        
        $em = $this->getDoctrine()->getManager();
        $productRepo = $em->getRepository('ShopBundle:Product');
        $categoryRepo = $em->getRepository('ShopBundle:Category');
        
        $categoryId = $category->getId();
        $products = $productRepo->searchProducts($categoryId, $orderByColumn, $orderByDirection, $limit, $offset);
        $popularProducts = $productRepo->searchProducts($categoryId, 'viewed', 'DESC', $limit, $offset);
        $lastProducts = $productRepo->searchProducts($categoryId, 'added', 'DESC', $limit, $offset);

        $productsCount = $productRepo->countAllProducts($categoryId);



        $maxPages = ceil($productsCount / $limit);

        return [
            'popularProducts' => $popularProducts,
            'lastProducts' => $lastProducts,
            'products' => $products,
            'page' => $page,
            'category' => $category,
            'column' => $orderByColumn,
            'direction' => $orderByDirection,
            'maxPages' => $maxPages,
            'base_dir' => realpath($this->getParameter('kernel.root_dir') . '/..')
        ];
    }
}
