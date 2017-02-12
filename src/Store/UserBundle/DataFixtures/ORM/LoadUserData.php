<?php

namespace Store\UserBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Store\ShopBundle\Entity\Customer;
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
        // Get our userManager`
        $userManager = $this->container->get('fos_user.user_manager');

        // Create our user  root   and set details
        $user = new Customer();
        $user->setUsername('root');
        $user->setEmail('root@saerver.com');
        $user->setFullname('Rooty');
        $user->setAddress('USA california 1234  drive');
        
        $user->setSalt(md5(uniqid()));
        
        //set plain password on encode it with encoder+salt !
        //$user->setPlainPassword('root');
        $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);
        $user->setPassword($encoder->encodePassword('root', $user->getSalt()));
        
        $user->setEnabled(true);
        $user->setRoles(array('ROLE_ADMIN'));

        // Update the user
        $userManager->updateUser($user, true);
        
        unset($user);
        
        // Create our user  root   and set details
        $user = new Customer();
        $user->setUsername('admin');
        $user->setEmail('admin@server.com');
        $user->setFullname('Admincheg');
        $user->setAddress('Administan cityOfAdmins 8bit drive 123');
        
        $user->setSalt(md5(uniqid()));
        
        //set plain password on encode it with encoder+salt !
        //$user->setPlainPassword('root');
        $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);
        $user->setPassword($encoder->encodePassword('admin', $user->getSalt()));;
        
        $user->setEnabled(true);
        $user->setRoles(array('ROLE_SUPER_ADMIN'));

        // Update the user
        $userManager->updateUser($user, true);
        unset($user);
        
        
        // Create our user  root   and set details
        $user = new Customer();
        $user->setUsername('Homo erectus');
        $user->setEmail('admin@server.com');
        $user->setFullname('User ');
        $user->setAddress('Userland Usercity Olduser Boulevard 123');
        
        $user->setSalt(md5(uniqid()));
        
        //set plain password on encode it with encoder+salt !
        //$user->setPlainPassword('root');
        $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);
        $user->setPassword($encoder->encodePassword('user', $user->getSalt()));;
        
        $user->setEnabled(true);
        $user->setRoles(array('ROLE_SUPER_ADMIN'));

        // Update the user
        $userManager->updateUser($user, true);
        unset($user);
        
    }
}
