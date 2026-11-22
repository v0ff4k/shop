<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса экспорта XML (админ панель).
 */
final class AdminXmlExportRequestDto
{
    #[Assert\Type(type: 'string', groups: ['Default'])]
    #[Assert\Regex(
        pattern: '/^[\d,\s]+$/',
        match: true,
        message: 'ID продуктов должны быть числами, разделенными запятыми',
        groups: ['Default']
    )]
    public readonly ?string $productIds;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 10000)] // Админ может экспортировать больше
    public readonly int $limit;

    #[Assert\Type(type: 'string', groups: ['Default'])]
    #[Assert\Length(max: 255)]
    public readonly ?string $outputPath;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 999999999999999)]
    public readonly ?int $userId;

    private function __construct(
        ?string $productIds = null,
        int $limit = 1000,
        ?string $outputPath = null,
        ?int $userId = null,
    ) {
        $this->productIds = $productIds;
        $this->limit = $limit;
        $this->outputPath = $outputPath;
        $this->userId = $userId;
    }

    public static function fromRequest(\Symfony\Component\HttpFoundation\Request $request): self
    {
        $userId = $request->request->getInt('export_user_id');
        $outputPath = $request->request->get('output_path');

        return new self(
            productIds: $request->request->get('product_ids'),
            limit: $request->request->getInt('limit', 1000),
            outputPath: \is_string($outputPath) ? (trim($outputPath) ?: null) : null,
            userId: $userId > 0 ? $userId : null
        );
    }

    /**
     * @return array<int, int>
     */
    public function getProductIdsArray(): array
    {
        if ($this->productIds === null || trim($this->productIds) === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', trim($this->productIds));
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
