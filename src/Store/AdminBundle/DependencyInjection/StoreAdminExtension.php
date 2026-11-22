<?php

declare(strict_types=1);

namespace App\Store\AdminBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Admin services moved to config/services_admin.yaml.
 */
final class StoreAdminExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // no bundle-owned service files; see config/services_admin.yaml
    }
}
