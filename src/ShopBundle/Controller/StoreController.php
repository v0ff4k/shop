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
class StoreController extends MainController
{

    private $perPageLimit;

    public function __construct()
    {
        $this->perPageLimit = $this->container->getParameter('product_perpage_limit');

    }


    /**
     * Start page with new + popular + category + able to search
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $productRepo = $em->getRepository('ShopBundle:Product');
        $categoryRepo = $em->getRepository('ShopBundle:Category');


        $categories = $categoryRepo->findAll();

        //not needed on mainpage
        //$products = $productRepo->searchProducts(false, $orderByColumn, $orderByDirection, $limit, $offset);
        $products = [];

        $popularProducts = $productRepo->searchProducts(false, 'viewed', 'DESC', $this->perPageLimit);
        $lastProducts = $productRepo->searchProducts(false, 'added', 'DESC', $this->perPageLimit);

        return [
            'popularProducts' => $popularProducts,
            'lastProducts' => $lastProducts,
            'products' => $products,
            'categories' => $categories,
            'base_dir' => realpath($this->getParameter('kernel.root_dir') . '/..')
        ];
    }
}
