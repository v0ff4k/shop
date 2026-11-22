<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfProtectionSubscriber implements EventSubscriberInterface
{
    public const TOKEN_ID = 'shop_action';

    private const PROTECTED_ROUTES = [
        'shop_cart_add',
        'shop_cart_update',
        'shop_cart_remove',
        'shop_cart_clear',
        'shop_order_create_post',
        'user_register_post',
        'user_profile_update',
    ];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['validateCsrfToken', 8],
        ];
    }

    public function validateCsrfToken(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethod(Request::METHOD_POST)) {
            return;
        }

        if (!\in_array($request->attributes->get('_route'), self::PROTECTED_ROUTES, true)) {
            return;
        }

        $submittedToken = (string) $request->request->get('_csrf_token', '');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $submittedToken))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }
}
