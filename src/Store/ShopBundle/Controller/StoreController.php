<?php

namespace Store\ShopBundle\Controller;

use Store\ShopBundle\Entity\Product;
use Store\ShopBundle\Entity\Category;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of StoreController
 * 
 * @Route("/")
 */
class StoreController extends Controller
{

    /**
     * Start page with new + popular + category + able to search
     * 
     * @Route(path = "", name="homepage")
     * @Template("StoreShopBundle:Store:index.html.twig")
     * @Method({"GET"})
     */
    public function indexAction()
    {
        $limit = $this->container->getParameter('product_perpage_limit');

        $em = $this->getDoctrine()->getManager();
        $productRepo = $em->getRepository('StoreShopBundle:Product');
        $categoryRepo = $em->getRepository('StoreShopBundle:Category');


        $categories = $categoryRepo->findAll();

        //not needed on mainpage
        //$products = $productRepo->searchProducts(false, $orderByColumn, $orderByDirection, $limit, $offset);
        $products = [];

        $popularProducts = $productRepo->searchProducts(false, 'viewed', 'DESC', $limit);
        $lastProducts = $productRepo->searchProducts(false, 'added', 'DESC', $limit);

        $productsCount = $productRepo->countAllProducts();

        return [
            'popularProducts' => $popularProducts,
            'lastProducts' => $lastProducts,
            'products' => $products,
            'categories' => $categories,
            'base_dir' => realpath($this->getParameter('kernel.root_dir') . '/..')
        ];
    }
}
