<?php

namespace ShopBundle\Controller;

use App\ShopBundle\Controller\MainController;
use ShopBundle\Entity\Product;
use ShopBundle\Entity\Category;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * Product controller.
 */
class ProductController extends MainController
{
   
    /**
     * Search trough product entities for matches or list all.
     */
    public function indexAction(Request $request)
    {
        $requestProduct = $request->get('q');
        
        if($requestProduct === null) {
            return $this->redirectToRoute('homepage');
        }
        
        $em = $this->getDoctrine()->getManager();
        $productRepo = $em->getRepository('ShopBundle:Product');
        $limit = $this->container->getParameter('product_perpage_limit');
        $products = $productRepo->searchRequestedProduct($requestProduct, $limit);

        return [
           'request' => $requestProduct,
           'products' => $products,
           ];
    }

    /**
     * Finds and displays a product entity by product id.
     */
    public function showAction(Product $product)
    {
        $em = $this->getDoctrine()->getManager();
        $product->setViewed($product->getViewed()  + 1);
        $product->setLastViewed(new \DateTime("now"));
        $em->persist($product);
        $em->flush();
        
        return [
            'product' => $product,
            ];
    }

      
}
