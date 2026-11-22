<?php

declare(strict_types=1);

namespace App\Store\ShopBundle\Controller;

use App\Store\ShopBundle\Entity\Product;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Product controller.
 *
 * @Route("product")
 */
class ProductController extends Controller
{
    /**
     * Search trough product entities for matches.
     *
     * @Route(path = "/", name="product_search")
     *
     * @Method({"GET", "POST"})
     *
     * @Template("shop/legacy/Product/index.html.twig")
     */
    public function indexAction(Request $request)
    {
        $requestProduct = $request->get('q');

        if ($requestProduct === null) {
            return $this->redirectToRoute('homepage');
        }

        $em = $this->getDoctrine()->getManager();
        $productRepo = $em->getRepository('StoreShopBundle:Product');
        $limit = $this->container->getParameter('product_perpage_limit');
        $products = $productRepo->searchRequestedProduct($requestProduct, $limit);

        return [
            'request' => $requestProduct,
            'products' => $products,
        ];
    }

    /**
     * Finds and displays a product entity by product id.
     *
     * @Route("/{slug}", name="product_show")
     *
     * @Method("GET")
     *
     * @Template("shop/legacy/Product/show.html.twig")
     */
    public function showAction(Product $product)
    {
        $em = $this->getDoctrine()->getManager();
        $product->setViewed($product->getViewed() + 1);
        $product->setLastViewed(new \DateTime('now'));
        $em->flush($product);

        return [
            'product' => $product,
        ];
    }
}
