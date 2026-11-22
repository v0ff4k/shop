<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для GET /admin/monitor/logs.
 */
final class MonitorLogsRequestDto
{
    #[Assert\Length(max: 64)]
    public readonly string $type;

    #[Assert\Range(min: 1, max: 200)]
    public readonly int $limit;

    private function __construct(string $type, int $limit)
    {
        $this->type = $type;
        $this->limit = $limit;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            type: trim((string) $request->query->get('type', '')),
            limit: max(1, min(200, $request->query->getInt('limit', 50)))
        );
    }
}
