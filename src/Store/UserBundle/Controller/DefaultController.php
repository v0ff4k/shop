<?php

namespace Store\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('StoreUserBundle:Default:index.html.twig');
    }
}
