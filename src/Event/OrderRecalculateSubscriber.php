<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\VarDumper\VarDumper;

class OrderRecalculateSubscriber implements EventSubscriberInterface
{
    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public static function getSubscribedEvents(): array
    {
        return [
//            Events::ORDER_CREATED => 'recalculate',
//            Events::ORDER_UPDATED => 'recalculate',
        ];
    }

    public function recalculate(GenericEvent $event): void
    {
        $entity = $event->getSubject();

        ////

    }
}