<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Users;
use App\Service\{AuditContext, SecurityAudit};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\{LoginSuccessEvent, LoginFailureEvent, LogoutEvent};

final class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private SecurityAudit $audit, private AuditContext $context)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLogin',
            LoginFailureEvent::class => 'onFailure',
            LogoutEvent::class => ['onLogout', -128],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onLogin(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $this->audit->record('auth.login_succeeded', $user instanceof Users ? $user->getId() : null);
    }

    public function onFailure(LoginFailureEvent $event): void
    {
        // A submitted email does not identify an authenticated actor.
        $this->audit->record('auth.login_failed', null);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        $this->audit->record('auth.logout', $user instanceof Users ? $user->getId() : null);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }
        $action = match ($event->getResponse()->getStatusCode()) {
            403 => 'access.denied',
            429 => 'access.rate_limited',
            default => null,
        };
        if ($action !== null) {
            $this->audit->record($action, $this->context->actorId());
        }
    }
}
