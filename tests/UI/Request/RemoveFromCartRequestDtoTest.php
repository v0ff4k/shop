<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\RemoveFromCartRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Тесты для DTO удаления товара из корзины.
 */
final class RemoveFromCartRequestDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidRemoveFromCartRequest(): void
    {
        $dto = RemoveFromCartRequestDto::fromInt(1);

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testRemoveFromCartRequestFromRoute(): void
    {
        $request = new Request();
        $request->attributes->set('productId', '42');

        $dto = RemoveFromCartRequestDto::fromRequest($request);

        $this->assertSame(42, $dto->productId);
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testRemoveFromCartRequestRequiresProductId(): void
    {
        $dto = RemoveFromCartRequestDto::fromInt(0);

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Product ID must be positive', $violations[0]->getMessage());
    }

    public function testRemoveFromCartRequestPositiveProductId(): void
    {
        $dto = RemoveFromCartRequestDto::fromInt(-5);

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Product ID must be positive', $violations[0]->getMessage());
    }
}
