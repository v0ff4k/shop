<?php

declare(strict_types=1);

namespace App\UI\Response;

/**
 * Response для отображения Twig шаблона
 * НЕ расширяет Response, чтобы Symfony вызвал событие kernel.view.
 */
final class ViewResponse
{
    public function __construct(
        private readonly string $template,
        private readonly array $context = []
    ) {
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
