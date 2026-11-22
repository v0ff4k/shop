<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\CreateOrderRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Тесты для DTO создания заказа.
 */
final class CreateOrderRequestDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidCreateOrderRequest(): void
    {
        $dto = CreateOrderRequestDto::fromJson($this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testCreateOrderRequestFromRequest(): void
    {
        $request = new Request(
            [],
            [
                'customer_name' => 'John Doe',
                'delivery_address' => '123 Main Street, Apt 4',
                'additional_info' => 'Ring the bell',
                'items' => [
                    ['product_id' => 1, 'quantity' => 2],
                    ['product_id' => 2, 'quantity' => 1],
                ],
            ],
            [],
            [],
            [],
            ['HTTP_X_IDEMPOTENCY_KEY' => 'order-key-123']
        );

        $dto = CreateOrderRequestDto::fromRequest($request);

        $this->assertSame('John Doe', $dto->customerName);
        $this->assertSame('123 Main Street, Apt 4', $dto->deliveryAddress);
        $this->assertSame('Ring the bell', $dto->additionalInfo);
        $this->assertSame([
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 1],
        ], $dto->items);
        $this->assertSame('order-key-123', $dto->getIdempotencyKey());
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testCreateOrderRequestRequiresCustomerName(): void
    {
        $dto = CreateOrderRequestDto::fromJson(['customer_name' => ''] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertSame(
            [
                'Customer name is required',
                'Name must be at least 2 characters',
            ],
            $this->messages($violations)
        );
    }

    public function testCreateOrderRequestRequiresDeliveryAddress(): void
    {
        $dto = CreateOrderRequestDto::fromJson(['delivery_address' => ''] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertSame(
            [
                'Delivery address is required',
                'Address must be at least 10 characters',
            ],
            $this->messages($violations)
        );
    }

    public function testCreateOrderRequestRequiresItems(): void
    {
        $dto = CreateOrderRequestDto::fromJson(['items' => []] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Order must contain at least one item', $violations[0]->getMessage());
    }

    public function testCreateOrderRequestItemsValidation(): void
    {
        $dto = CreateOrderRequestDto::fromJson([
            'items' => [
                ['product_id' => 0, 'quantity' => 2],
            ],
        ] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('This value should be positive.', $violations[0]->getMessage());
        $this->assertSame('items[0][product_id]', $violations[0]->getPropertyPath());
    }

    public function testCreateOrderRequestItemRequiresQuantity(): void
    {
        $dto = CreateOrderRequestDto::fromJson([
            'items' => [
                ['product_id' => 1],
            ],
        ] + $this->validPayload());

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('This field is missing.', $violations[0]->getMessage());
        $this->assertSame('items[0][quantity]', $violations[0]->getPropertyPath());
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'customer_name' => 'John Doe',
            'delivery_address' => '123 Main Street, Apt 4',
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function messages(ConstraintViolationListInterface $violations): array
    {
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages;
    }
}
