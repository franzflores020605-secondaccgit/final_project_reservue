<?php

namespace App\EventSubscriber;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $this->auditLogger->log('Login', 'Auth', null, 'User logged in');
    }

    public function onLogout(LogoutEvent $event): void
    {
        $this->auditLogger->log('Logout', 'Auth', null, 'User logged out');
    }
}

