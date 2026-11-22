<?php

declare(strict_types=1);

namespace App\Tests\UI\Resolver;

use App\UI\Request\ProductSuggestRequestDto;
use App\UI\Resolver\RequestDtoResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestDtoResolverTest extends TestCase
{
    public function testResolveCreatesAndValidatesSuggestDto(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with($this->isInstanceOf(ProductSuggestRequestDto::class))
            ->willReturn(new ConstraintViolationList());

        $resolver = new RequestDtoResolver(
            $validator,
            $this->createMock(SerializerInterface::class)
        );

        $request = new Request(['q' => 'plastic', 'limit' => '5']);
        $argument = new ArgumentMetadata('dto', ProductSuggestRequestDto::class, false, false, null);

        $values = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $values);
        $this->assertInstanceOf(ProductSuggestRequestDto::class, $values[0]);
        $this->assertSame('plastic', $values[0]->query);
        $this->assertSame(5, $values[0]->limit);
    }

    public function testResolveRejectsInvalidSuggestDto(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList([
                new ConstraintViolation(
                    'This value is too short.',
                    null,
                    [],
                    null,
                    'query',
                    ''
                ),
            ]));

        $resolver = new RequestDtoResolver(
            $validator,
            $this->createMock(SerializerInterface::class)
        );

        $this->expectException(BadRequestHttpException::class);

        $request = new Request(['q' => 'a', 'limit' => '5']);
        $argument = new ArgumentMetadata('dto', ProductSuggestRequestDto::class, false, false, null);

        iterator_to_array($resolver->resolve($request, $argument));
    }
}
