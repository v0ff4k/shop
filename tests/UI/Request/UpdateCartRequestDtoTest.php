<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\UpdateCartRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Тесты для DTO обновления корзины.
 */
final class UpdateCartRequestDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidUpdateCartRequest(): void
    {
        $dto = $this->fromRoute(1, '2');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testUpdateCartRequestRequiresProductId(): void
    {
        $dto = $this->fromRoute(0, '2');

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Product ID must be positive', $violations[0]->getMessage());
    }

    public function testUpdateCartRequestRequiresPositiveProductId(): void
    {
        $dto = $this->fromRoute(-5, '2');

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Product ID must be positive', $violations[0]->getMessage());
    }

    public function testUpdateCartRequestClampsQuantityToMinimum(): void
    {
        $dto = $this->fromRoute(1, '0');

        $this->assertSame(1, $dto->quantity);
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testUpdateCartRequestQuantityRange(): void
    {
        $dto = $this->fromRoute(1, '1000');

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('This value should be between 1 and 999.', $violations[0]->getMessage());
    }

    private function fromRoute(int $productId, string $quantity): UpdateCartRequestDto
    {
        $request = new Request([], ['quantity' => $quantity]);
        $request->attributes->set('productId', $productId);

        return UpdateCartRequestDto::fromRequest($request);
    }
}
