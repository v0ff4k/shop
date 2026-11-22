<?php

declare(strict_types=1);

namespace App\Store\ShopBundle\Controller;

use App\Store\ShopBundle\Entity\Category;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Description of StoreController.
 *
 * @Route("category")
 */
class CategoryController extends Controller
{
    /**
     * Show category with products by specific id or title.
     *
     * @Route(
     *      path = "/{slug}/{page}/{orderByColumn}/{orderByDirection}",
     *      name="category_show",
     *      requirements = {
     *          "page" = "\d*",
     *          "orderByColumn" = "\w*",
     *          "orderByDirection" = "(ASC|DESC)",
     *      },
     *      defaults = {
     *          "page" = "1",
     *          "orderByColumn" = "added",
     *          "orderByDirection" = "DESC",
     *      },
     * )
     *
     * @Template("shop/legacy/Category/show.html.twig")
     *
     * @Method({"GET"})
     */
    public function showAction(Category $category, $page, $orderByColumn, $orderByDirection)
    {
        $limit = $this->container->getParameter('product_perpage_limit');

        // set offset=0, if page =< 1, else default $offset
        $offset = ($page <= 1) ? 0 : (($page - 1) * $limit);

        $em = $this->getDoctrine()->getManager();
        $productRepo = $em->getRepository('StoreShopBundle:Product');
        $categoryRepo = $em->getRepository('StoreShopBundle:Category');

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
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ];
    }
}
