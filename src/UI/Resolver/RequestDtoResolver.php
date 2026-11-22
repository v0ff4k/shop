<?php

declare(strict_types=1);

namespace App\UI\Resolver;

use App\UI\Request\AddToCartRequestDto;
use App\UI\Request\AdminXmlExportRequestDto;
use App\UI\Request\AdminXmlImportRequestDto;
use App\UI\Request\AsyncJobListRequestDto;
use App\UI\Request\CategoryViewRequestDto;
use App\UI\Request\CreateOrderRequestDto;
use App\UI\Request\IdempotentRequestDto;
use App\UI\Request\LoginRequestDto;
use App\UI\Request\MonitorLogsRequestDto;
use App\UI\Request\ProductListRequestDto;
use App\UI\Request\ProductSearchRequestDto;
use App\UI\Request\ProductShowRequestDto;
use App\UI\Request\ProductSuggestRequestDto;
use App\UI\Request\ProductViewRequestDto;
use App\UI\Request\RefreshTokenRequestDto;
use App\UI\Request\RegisterUserRequestDto;
use App\UI\Request\RemoveFromCartRequestDto;
use App\UI\Request\UpdateCartRequestDto;
use App\UI\Request\UpdateProfileRequestDto;
use App\UI\Request\XmlExportRequestDto;
use App\UI\Request\XmlImportRequestDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Resolver для автоматического создания и валидации DTO из запроса
 * Заполняется из scripts/find_dtos_for_resolver.sh(общий список).
 *
 * Используется Symfony для автоматического внедрения DTO в контроллеры
 */
final class RequestDtoResolver implements ValueResolverInterface
{
    /**
     * @var array<class-string, callable>
     */
    private const DTO_CREATORS = [
        AddToCartRequestDto::class => [AddToCartRequestDto::class, 'fromRequest'],

        AdminXmlExportRequestDto::class => [AdminXmlExportRequestDto::class, 'fromRequest'],
        AdminXmlImportRequestDto::class => [AdminXmlImportRequestDto::class, 'fromRequest'], // or "createFromFiles" ?

        AsyncJobListRequestDto::class => [AsyncJobListRequestDto::class, 'fromRequest'],
        CategoryViewRequestDto::class => [CategoryViewRequestDto::class, 'fromRequest'],
        CreateOrderRequestDto::class => [CreateOrderRequestDto::class, 'fromRequest'],
        IdempotentRequestDto::class => [IdempotentRequestDto::class, 'fromRequest'],
        LoginRequestDto::class => [LoginRequestDto::class, 'fromRequest'],
        MonitorLogsRequestDto::class => [MonitorLogsRequestDto::class, 'fromRequest'],
        ProductListRequestDto::class => [ProductListRequestDto::class, 'fromRequest'],
        ProductSearchRequestDto::class => [ProductSearchRequestDto::class, 'fromRequest'],
        ProductShowRequestDto::class => [ProductShowRequestDto::class, 'fromRequest'],
        ProductSuggestRequestDto::class => [ProductSuggestRequestDto::class, 'fromRequest'],
        ProductViewRequestDto::class => [ProductViewRequestDto::class, 'fromRequest'],
        RefreshTokenRequestDto::class => [RefreshTokenRequestDto::class, 'fromRequest'],
        RegisterUserRequestDto::class => [RegisterUserRequestDto::class, 'fromRequest'],
        RemoveFromCartRequestDto::class => [RemoveFromCartRequestDto::class, 'fromRequest'],
        UpdateCartRequestDto::class => [UpdateCartRequestDto::class, 'fromRequest'],
        UpdateProfileRequestDto::class => [UpdateProfileRequestDto::class, 'fromRequest'],
        XmlExportRequestDto::class => [XmlExportRequestDto::class, 'fromRequest'],
        XmlImportRequestDto::class => [XmlImportRequestDto::class, 'fromRequest'], // or "createFromFiles" ?
    ];

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @return iterable<object>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type === null || !isset(self::DTO_CREATORS[$type])) {
            return [];
        }

        try {
            // Создаем DTO в зависимости от типа запроса
            $dto = $this->createDto($request, $type);

            // Валидируем DTO
            $this->validate($dto);

            yield $dto;
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (UnprocessableEntityHttpException $e) {
            // Пробрасываем дальше — валидация уже сформировала ответ TODO WHAT DO WITH UnprocessableEntityHttpException ????
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Failed to create request DTO: '.$e->getMessage());
        }
    }

    /**
     * Создать DTO из запроса.
     *
     * @param class-string $type
     */
    private function createDto(Request $request, string $type): object
    {
        $creator = self::DTO_CREATORS[$type];

        // Для JSON запросов (API)
        if (str_starts_with($request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->createFromJson($request, $type);
        }

        // Для обычных POST/GET запросов
        // Проверяем есть ли у метода fromRequest дополнительные параметры
        $reflection = new \ReflectionMethod($creator[0], $creator[1]);
        $params = $reflection->getParameters();

        // Если метод требует больше чем 1 параметр (первый всегда Request)
        if (\count($params) > 1) {
            $args = [$request];
            // Получаем дополнительные параметры из маршрута
            for ($i = 1; $i < \count($params); ++$i) {
                $paramName = $params[$i]->getName();
                $args[] = $request->attributes->get($paramName);
            }

            return $creator(...$args);
        }

        return $creator($request);
    }

    /**
     * Создать DTO из JSON.
     *
     * @param class-string $type
     */
    private function createFromJson(Request $request, string $type): object
    {
        try {
            $content = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON format');
        }

        // Для LoginRequestDto и других с методом fromJson
        if (method_exists($type, 'fromJson')) {
            return $type::fromJson($content);
        }

        // Используем сериализатор для остальных
        return $this->serializer->denormalize($content, $type, 'json');
    }

    /**
     * Валидировать DTO.
     *
     * @throws UnprocessableEntityHttpException
     */
    private function validate(object $dto): void
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
}
