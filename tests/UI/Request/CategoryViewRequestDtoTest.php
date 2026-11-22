<?php

declare(strict_types=1);

namespace App\Tests\UI\Request;

use App\UI\Request\CategoryViewRequestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Тесты для DTO просмотра категории.
 */
final class CategoryViewRequestDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidCategoryViewRequest(): void
    {
        $dto = $this->fromRoute('my-category');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testCategoryViewRequestRequiresSlug(): void
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

    public function testCategoryViewRequestSlugFormat(): void
    {
        $dto = $this->fromRoute('Invalid Slug!');

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('Slug must be a valid URL-friendly string', $violations[0]->getMessage());
    }

    public function testCategoryViewRequestPageValidation(): void
    {
        $dto = $this->fromRoute('my-category', ['page' => '10001']);

        $violations = $this->validator->validate($dto);

        $this->assertCount(1, $violations);
        $this->assertSame('This value should be between 1 and 10000.', $violations[0]->getMessage());
    }

    public function testCategoryViewRequestClampsPagination(): void
    {
        $dto = $this->fromRoute('my-category', ['page' => '0', 'limit' => '1000']);

        $this->assertSame(1, $dto->page);
        $this->assertSame(100, $dto->limit);
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testCategoryViewRequestOffset(): void
    {
        $dto = $this->fromRoute('my-category', ['page' => '3', 'limit' => '10']);

        $this->assertSame(20, $dto->getOffset());
    }

    /**
     * @param array<string, string> $query
     */
    private function fromRoute(string $slug, array $query = []): CategoryViewRequestDto
    {
        $request = new Request($query);
        $request->attributes->set('slug', $slug);

        return CategoryViewRequestDto::fromRequest($request);
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
