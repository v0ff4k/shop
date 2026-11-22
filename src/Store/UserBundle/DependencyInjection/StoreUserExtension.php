<?php

declare(strict_types=1);

namespace App\Store\UserBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * StoreUserBundle has no bundle-owned service definitions.
 */
final class StoreUserExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // no services.yml in Resources/config; nothing to load
    }
}
