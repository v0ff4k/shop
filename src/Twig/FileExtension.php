<?php

declare(strict_types=1);

namespace App\Twig;

use App\Infrastructure\Helper\PathHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig расширение для работы с файлами
 * Делегирует всю логику PathHelper.
 */
final class FileExtension extends AbstractExtension
{
    public function __construct(
        private readonly PathHelper $pathHelper
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_upload', [$this->pathHelper, 'normalize']),
            new TwigFunction('asset_thumb_upload', [$this->pathHelper, 'thumbUrl']),
            new TwigFunction('product_image_url', [$this->pathHelper, 'productImageUrl']),
            new TwigFunction('product_thumb_url', [$this->pathHelper, 'productThumbUrl']),
            new TwigFunction('category_image_url', [$this->pathHelper, 'categoryImageUrl']),
            new TwigFunction('category_thumb_url', [$this->pathHelper, 'categoryThumbUrl']),
        ];
    }
}
