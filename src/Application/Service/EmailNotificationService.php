<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\SendEmailCommand;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;

/**
 * Сервис для отправки email уведомлений.
 */
final class EmailNotificationService
{
    public function __construct(
        private readonly MessageBusInterface $emailBus,
        private readonly Environment $twig,
        private readonly string $fromEmail
    ) {
    }

    /**
     * Отправить email о регистрации пользователя.
     */
    public function sendWelcomeEmail(string $to, string $username): void
    {
        $this->sendEmail(
            to: $to,
            subject: 'Добро пожаловать в SHOP!',
            template: 'emails/welcome.html.twig',
            context: [
                'username' => $username,
            ]
        );
    }

    /**
     * Отправить email асинхронно.
     */
    public function sendEmail(
        string $to,
        string $subject,
        string $template,
        array $context = []
    ): void {
        // Рендерим HTML шаблон
        $htmlBody = $this->twig->render($template, $context);

        // Отправляем команду в очередь
        $this->emailBus->dispatch(
            new SendEmailCommand(
                to: $to,
                subject: $subject,
                body: '', // Текстовая версия (опционально)
                htmlBody: $htmlBody,
                context: $context
            ));
    }

    /**
     * Отправить email о подтверждении заказа.
     */
    public function sendOrderConfirmation(string $to, string $orderNumber, float $total): void
    {
        $this->sendEmail(
            to: $to,
            subject: \sprintf('Заказ %s подтверждён', $orderNumber),
            template: 'emails/order_confirmation.html.twig',
            context: [
                'orderNumber' => $orderNumber,
                'total' => $total,
            ]
        );
    }

    /**
     * Отправить email о сбросе пароля.
     */
    public function sendPasswordReset(string $to, string $resetToken): void
    {
        $this->sendEmail(
            to: $to,
            subject: 'Сброс пароля',
            template: 'emails/password_reset.html.twig',
            context: [
                'resetToken' => $resetToken,
            ]
        );
    }

    /**
     * Отправить email продавцу о новом заказе.
     */
    public function sendNewOrderNotification(string $to, string $orderNumber, float $total): void
    {
        $this->sendEmail(
            to: $to,
            subject: \sprintf('Новый заказ %s', $orderNumber),
            template: 'emails/new_order_notification.html.twig',
            context: [
                'orderNumber' => $orderNumber,
                'total' => $total,
            ]
        );
    }
}
