<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса списка асинхронных задач.
 */
final class AsyncJobListRequestDto
{
    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 100)]
    public readonly int $limit;

    #[Assert\Type(type: 'string', groups: ['Default'])]
    #[Assert\Length(max: 50)]
    public readonly ?string $type;

    private function __construct(int $limit = 20, ?string $type = null)
    {
        $this->limit = $limit;
        $this->type = $type;
    }

    public static function fromRequest(\Symfony\Component\HttpFoundation\Request $request): self
    {
        return new self(
            limit: $request->query->getInt('limit', 20),
            type: $request->query->get('type')
        );
    }
}
