<?php

namespace Store\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class StoreUserBundle extends Bundle
{

    public function getParent()
    {
        return 'FOSUserBundle';
    }
}