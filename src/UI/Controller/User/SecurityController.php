<?php

declare(strict_types=1);

namespace App\UI\Controller\User;

use App\UI\Controller\ControllerInterface;
use App\UI\Response\RedirectResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

/**
 * Контроллер безопасности (вход/выход).
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class SecurityController implements ControllerInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly Environment $twig
    ) {
    }

    /**
     * GET/POST /login - Показать форму входа
     * POST вызывается только при ошибке аутентификации (firewall редиректит обратно).
     */
    public function login(AuthenticationUtils $authenticationUtils): Response|RedirectResponse
    {
        // Если пользователь уже авторизован
        if ($this->security->getUser()) {
            return new RedirectResponse('shop_homepage', [], 'info', 'Вы уже авторизованы');
        }

        // Запускаем сессию для CSRF токена
        $request = $this->requestStack->getMainRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            if (!$session->isStarted()) {
                $session->start();
                // Записываем маркер чтобы сессия не была пустой и cookie отправилась
                $session->set('_login_page_visited', true);
            }
        }

        $content = $this->twig->render('user/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);

        return new Response($content);
    }

    /**
     * GET /logout - Выход.
     *
     * Перехватывается Symfony Security firewall
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
