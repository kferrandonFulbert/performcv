<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AttemptLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(#[Autowire('%kernel.cache_dir%')] private string $cacheDir)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['limit', 10]];
    }

    public function limit(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST') || !in_array($request->attributes->get('_route'), ['app_login', 'app_register', 'app_public_share'], true)) {
            return;
        }
        // Atomic per-IP fixed windows on a single host. Share this storage for multi-node deployments.
        $directory = $this->cacheDir.'/attempts';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            $event->setResponse(new Response('Réessayez ultérieurement.', 503));

            return;
        }
        $window = intdiv(time(), 900);
        $file = $directory.'/'.hash('sha256', $request->getClientIp() ?? 'unknown').'.json';
        $handle = fopen($file, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            if ($handle) {
                fclose($handle);
            }
            $event->setResponse(new Response('Réessayez ultérieurement.', 503));

            return;
        }
        try {
            $state = json_decode(stream_get_contents($handle) ?: '{}', true);
            $count = is_array($state) && ($state['window'] ?? null) === $window ? (int) ($state['count'] ?? 0) : 0;
            ++$count;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(['window' => $window, 'count' => $count], JSON_THROW_ON_ERROR));
            if ($count > 20) {
                $event->setResponse(new Response('Trop de tentatives. Réessayez dans quelques minutes.', 429, ['Retry-After' => (string) (900 - time() % 900)]));
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
