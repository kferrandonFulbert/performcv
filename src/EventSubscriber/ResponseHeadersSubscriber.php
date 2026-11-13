<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        $request = $event->getRequest();
        $response = $event->getResponse();
        $editorial = in_array($request->attributes->get('_route'), [
            'app_home', 'app_legal', 'app_privacy', 'app_terms', 'app_cookies',
            'app_cv_etudiant', 'app_cv_alternance', 'app_cv_stage', 'app_cv_developpeur_web',
            'app_cv_sans_experience', 'app_cv_ats', 'app_cv_bts_sio', 'app_lettre_alternance',
        ], true);
        if ($editorial) {
            $response->setVary(['Cookie', 'Authorization'], false);
            if ($request->isMethodCacheable() && 200 === $response->getStatusCode()
                && !$request->cookies->count() && !$request->headers->has('Cookie')
                && !$request->headers->has('Authorization') && !$headers->getCookies()
                && !($request->hasSession(true) && $request->getSession()->isStarted())) {
                $headers->set('Cache-Control', 'public, max-age=0, s-maxage=300');
                return;
            }
        }
        $response->setPrivate();
        $headers->set('Cache-Control', 'private, no-store');
    }
}
