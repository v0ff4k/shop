<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use App\UI\Response\RedirectResponse;
use App\UI\Response\ViewResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Обработчик Response контроллеров
 * Преобразует ViewResponse и RedirectResponse в HTTP Response.
 */
#[AsEventListener(event: KernelEvents::VIEW, method: 'onKernelView', priority: 10)]
final class ControllerResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['onKernelView', 10],
        ];
    }

    public function onKernelView(ViewEvent $event): void
    {
        $controllerResult = $event->getControllerResult();

        // Если контроллер вернул null - пропускаем
        if ($controllerResult === null) {
            $this->logger->warning('Controller returned null');

            return;
        }

        // Обработка ViewResponse
        if ($controllerResult instanceof ViewResponse) {
            try {
                $html = $this->twig->render(
                    $controllerResult->getTemplate(),
                    $controllerResult->getContext()
                );

                $event->setResponse(new Response($html));
            } catch (\Exception $e) {
                $this->logger->error('Twig render error: '.$e->getMessage(), [
                    'template' => $controllerResult->getTemplate(),
                    'exception' => $e,
                ]);
                $event->setResponse(new Response('Template error: '.$e->getMessage(), 500));
            }

            return;
        }

        // Обработка RedirectResponse с flash сообщениями
        if ($controllerResult instanceof RedirectResponse) {
            try {
                $url = $this->urlGenerator->generate(
                    $controllerResult->route,
                    $controllerResult->parameters
                );

                $response = new SymfonyRedirectResponse($url);

                // Добавляем flash сообщение через сессию
                $session = $event->getRequest()->getSession();
                if ($session !== null) {
                    $session->getFlashBag()->add(
                        $controllerResult->flashType ?: 'success',
                        $controllerResult->flashMessage
                    );
                }

                $event->setResponse($response);
            } catch (\Exception $e) {
                $this->logger->error('Redirect error: '.$e->getMessage(), [
                    'route' => $controllerResult->route,
                    'exception' => $e,
                ]);
                // Фоллбэк - редирект на главную
                $event->setResponse(new SymfonyRedirectResponse('/'));
            }
        }
    }
}
