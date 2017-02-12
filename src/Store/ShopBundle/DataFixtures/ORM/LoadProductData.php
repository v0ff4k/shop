<?php

namespace Store\ShopBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Store\ShopBundle\Entity\Product;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LoadUserData
 * @package Store\UserBundle\DataFixtures\ORM
 */
class LoadUserData implements FixtureInterface, ContainerAwareInterface
{

    private $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function load(ObjectManager $manager)
    {

        //fill with 8bit products without images
        for ($i = 0; $i < 20; $i++) {
            
            $product = new Product();
            $product->setCategory('4');
            $price = $i.".".rand(11, 99);///price range 1.11 ~ 19.99
            $price = (int)$price;
            $product->setPrice($price);

            $manager->persist($product);
            $manager->flush();
            unset($product);
            
        }
        
    }

    
    
}
