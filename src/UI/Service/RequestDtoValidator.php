<?php

declare(strict_types=1);

namespace App\UI\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Сервис для валидации и десериализации DTO запросов.
 */
final class RequestDtoValidator
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Создать DTO из JSON или Request в зависимости от Content-Type.
     *
     * @template T
     *
     * @param class-string<T> $dtoClass
     *
     * @return T
     */
    public function create(Request $request, string $dtoClass): object
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            if (str_starts_with($request->headers->get('Content-Type', ''), 'application/json')) {
                return $this->createFromJson($request, $dtoClass);
            }
        }

        return $this->createFromRequest($request, $dtoClass);
    }

    /**
     * Создать DTO из JSON запроса с валидацией.
     *
     * @template T
     *
     * @param class-string<T> $dtoClass
     *
     * @throws BadRequestHttpException
     * @throws UnprocessableEntityHttpException
     *
     * @return T
     */
    public function createFromJson(Request $request, string $dtoClass): object
    {
        try {
            $content = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON format');
        }

        /** @var object $dto */
        $dto = $this->serializer->denormalize($content, $dtoClass, 'json');

        $this->validate($dto);

        return $dto;
    }

    /**
     * Валидировать DTO и вернуть ошибки если есть.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function validate(object $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                $message = $violation->getMessage();

                if (isset($errors[$propertyPath])) {
                    $errors[$propertyPath][] = $message;
                } else {
                    $errors[$propertyPath] = [$message];
                }
            }

            throw new UnprocessableEntityHttpException(json_encode(['errors' => $errors, 'message' => 'Validation failed']));
        }
    }

    /**
     * Создать DTO из Request с валидацией.
     *
     * @template T
     *
     * @param class-string<T> $dtoClass
     *
     * @throws UnprocessableEntityHttpException
     *
     * @return T
     */
    public function createFromRequest(Request $request, string $dtoClass): object
    {
        if (!method_exists($dtoClass, 'fromRequest')) {
            throw new \InvalidArgumentException("DTO class {$dtoClass} must have fromRequest method");
        }

        /** @var object $dto */
        $dto = $dtoClass::fromRequest($request);

        $this->validate($dto);

        return $dto;
    }
}
