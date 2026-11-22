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
 * @Route("/")
 */
class StoreController extends Controller
{
    /**
     * Start page with new + popular + category + able to search.
     *
     * @Route(path = "", name="homepage")
     *
     * @Template("shop/legacy/Store/index.html.twig")
     *
     * @Method({"GET"})
     */
    public function indexAction()
    {
        $limit = $this->container->getParameter('product_perpage_limit');

        $em = $this->getDoctrine()->getManager();
        $productRepo = $em->getRepository('StoreShopBundle:Product');
        $categoryRepo = $em->getRepository('StoreShopBundle:Category');

        $categories = $categoryRepo->findAll();

        // not needed on mainpage
        // $products = $productRepo->searchProducts(false, $orderByColumn, $orderByDirection, $limit, $offset);
        $products = [];

        $popularProducts = $productRepo->searchProducts(false, 'viewed', 'DESC', $limit);
        $lastProducts = $productRepo->searchProducts(false, 'added', 'DESC', $limit);

        $productsCount = $productRepo->countAllProducts();

        return [
            'popularProducts' => $popularProducts,
            'lastProducts' => $lastProducts,
            'products' => $products,
            'categories' => $categories,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ];
    }
}
