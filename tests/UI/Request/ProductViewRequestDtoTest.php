<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\ProductViewRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Тесты для DTO просмотра товара.
 */
final class ProductViewRequestDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidProductViewRequest(): void
    {
        $dto = $this->fromRoute('my-awesome-product');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testProductViewRequestRequiresSlug(): void
    {
        $dto = $this->fromRoute('');

        $violations = $this->validator->validate($dto);

        $this->assertSame(
            [
                'This value should not be blank.',
                'This value is too short. It should have 1 character or more.',
            ],
            $this->messages($violations)
        );
    }

    public function testProductViewRequestSlugFormat(): void
    {
        $dto = $this->fromRoute('Invalid Slug!');

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Slug must be a valid URL-friendly string', $violations[0]->getMessage());
    }

    private function fromRoute(string $slug): ProductViewRequestDto
    {
        $request = new Request();
        $request->attributes->set('slug', $slug);

        return ProductViewRequestDto::fromRequest($request);
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
