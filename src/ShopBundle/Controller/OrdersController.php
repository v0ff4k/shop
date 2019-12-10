<?php

namespace ShopBundle\Controller;

use App\ShopBundle\Controller\MainController;
use ShopBundle\Entity\Orders;
use ShopBundle\Entity\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * Orders controller.
 */
class OrdersController extends MainController
{

    /**
     * Index of cart with products if added.
     */
    public function indexAction()
    {
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();
        $orders = $em->getRepository('ShopBundle:Orders')->findBy([ 'customer' => $user, 'sold' => Orders::SOLD_PREPARED]);

        return [
            'orders' => $orders,
        ];
    }

    /**
     * Add to cart product and continue checkout .
     */
    public function newAction(Product $product)
    {
        $now = new \DateTime();

        $order = new Orders();
        $user = $this->getUser();

        $order
            ->setProduct($product)
            ->setCustomer($user)
            ->setSold(Orders::SOLD_PREPARED)
            ->setShipping($user->getAddress())
            ->setQuantity(1)
            ->setCreated($now)
            ->setEdited($now);

        $em = $this->getDoctrine()->getManager();
        $em->persist($order);
        $em->flush();

        $this->get('session')->getFlashBag()->add('notice', 'Product "' . $product->getTitle() . '" added to your cart! ');

        $params = $this->getRefererParams();

        return $this->redirect(
            $this->generateUrl(//'order_index'
                    $params['_route'], [ 'slug' => $params['slug']]
            )
        );
    }

    /**
     * Displays a form to edit an existing order entity.
     */
    public function editAction(Request $request, Orders $order)
    {
        $editForm = $this->createForm('ShopBundle\Form\OrdersType', $order);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {

            $em = $this->getDoctrine()->getManager();

            $product = $order->getProduct(); //for update oldone
            $productQuantities = $product->getAvailable();
            $postedQuantity = $order->getQuantity();
            $soldState = $order->getSold();
            $user = $this->getUser()->getId();
            $owner = $order->getCustomer()->getId();

            //Sanitize full input data to proceed if its fully correct 
            if (($productQuantities < $postedQuantity) || $soldState != Orders::SOLD_DONE || $user != $owner) {
                $this->get('session')->getFlashBag()->add('warning', 'Product "<b>' . $product->getTitle() . '</b>" Error with inputted data, plase check!');

                return [
                    'order' => $order,
                    'edit_form' => $editForm->createView()
                ];
            }

            $order->setSold(Orders::SOLD_DONE);
            $product = $order->getProduct();
            $product->setAvailable($productQuantities - $postedQuantity);

            //write Order and Product UPDATED into DB
            $em->persist($order);
            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('order_index');
        }

        return [
            'order' => $order,
            'edit_form' => $editForm->createView(),
        ];
    }

    /**
     * Deletes a order entity.
     */
    public function deleteAction(Orders $order)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($order);
        $em->flush();

        return $this->redirectToRoute('order_index');
    }

    private function getRefererParams()
    {
        $request = $this->getRequest();
        $referer = $request->headers->get('referer');
        $baseUrl = $request->getBaseUrl();
        $lastPath = substr($referer, strpos($referer, $baseUrl) + strlen($baseUrl));
        return $this->get('router')->getMatcher()->match($lastPath);
    }
}
