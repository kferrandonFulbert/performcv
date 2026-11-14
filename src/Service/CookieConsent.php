<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Cookie, Request};

/**
 * Signe et vérifie les préférences de consentement pour la mesure d’audience.
 */
final class CookieConsent
{
    public const COOKIE_NAME = 'performcv_consent';
    public const POLICY_VERSION = '2026-11-13-v3';

    public function __construct(#[Autowire('%kernel.secret%')] private string $secret)
    {
    }

    /** @return array{analytics: bool, marketing: bool}|null */
    public function preferences(Request $request): ?array
    {
        $value = $request->cookies->get(self::COOKIE_NAME);
        if (!is_string($value) || strlen($value) > 256) {
            return null;
        }
        $parts = explode('.', $value);
        if (count($parts) !== 6) {
            return null;
        }
        [$version, $issued, $expires, $analytics, $marketing, $signature] = $parts;
        $payload = implode('.', array_slice($parts, 0, 5));
        if ($version !== self::POLICY_VERSION || !preg_match('/^[0-9]{10}$/D', $issued) || !preg_match('/^[0-9]{10}$/D', $expires)
            || !hash_equals(hash_hmac('sha256', $payload, $this->secret), $signature)
            || (int) $issued > time() || (int) $expires <= time()
            || (int) $expires > (new \DateTimeImmutable('@'.$issued))->modify('+6 months')->getTimestamp()
            || !in_array($analytics, ['0', '1'], true) || !in_array($marketing, ['0', '1'], true)) {
            return null;
        }

        return ['analytics' => $analytics === '1', 'marketing' => $marketing === '1'];
    }

    public function analyticsAllowed(Request $request): bool
    {
        $route = $request->attributes->getString('_route');
        $editorial = in_array($route, ['app_home', 'app_legal', 'app_privacy', 'app_terms'], true)
            || isset(PublicSeo::GUIDE_LINKS[$route]);
        return $editorial && ($this->preferences($request)['analytics'] ?? false);
    }

    public function cookie(bool $analytics, bool $marketing, bool $secure): Cookie
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expires = $now->modify('+6 months');
        $payload = implode('.', [self::POLICY_VERSION, $now->getTimestamp(), $expires->getTimestamp(), (int) $analytics, (int) $marketing]);

        return Cookie::create(self::COOKIE_NAME)
            ->withValue($payload.'.'.hash_hmac('sha256', $payload, $this->secret))
            ->withExpires($expires)
            ->withPath('/')
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
