<?php

declare(strict_types=1);

namespace App\Store\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('user/legacy/Default/index.html.twig');
    }
}
