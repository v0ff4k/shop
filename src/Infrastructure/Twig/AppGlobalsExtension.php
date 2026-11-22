<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Application\Query\CartQueryService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Добавляет глобальные переменные в Twig.
 */
final class AppGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly CartQueryService $cartQueryService
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'cart_count' => $this->cartQueryService->getCartCount(),
        ];
    }
}
