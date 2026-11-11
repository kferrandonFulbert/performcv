<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CookieConsent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CookieConsentTest extends TestCase
{
    public function testNoChoiceAndMalformedValuesDoNotAuthorizeTracking(): void
    {
        $consent = new CookieConsent('synthetic-test-secret');
        self::assertNull($consent->preferences(new Request()));
        foreach (['invalid', str_repeat('a', 300), '2026-11-11.'.str_repeat('9', 80).'.1.1.1.signature'] as $value) {
            self::assertNull($consent->preferences(new Request(cookies: [CookieConsent::COOKIE_NAME => $value])));
        }
    }

    public function testPreferencesAreSignedAndCookieIsRestricted(): void
    {
        $consent = new CookieConsent('synthetic-test-secret');
        $cookie = $consent->cookie(true, false, true);
        self::assertSame(['analytics' => true, 'marketing' => false], $consent->preferences(new Request(cookies: [CookieConsent::COOKIE_NAME => $cookie->getValue()])));
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertLessThanOrEqual((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+6 months')->getTimestamp(), $cookie->getExpiresTime());
        self::assertNull((new CookieConsent('different-secret'))->preferences(new Request(cookies: [CookieConsent::COOKIE_NAME => $cookie->getValue()])));
        $parts = explode('.', $cookie->getValue());
        $parts[4] = '1';
        self::assertNull($consent->preferences(new Request(cookies: [CookieConsent::COOKIE_NAME => implode('.', $parts)])));
    }

    public function testExpiredAndPreviousPolicyChoicesAreIgnored(): void
    {
        $secret = 'synthetic-test-secret';
        $consent = new CookieConsent($secret);
        foreach ([CookieConsent::POLICY_VERSION.'.'.(time() - 300).'.'.(time() - 1).'.1.1', 'old-policy.'.time().'.'.(time() + 3600).'.1.1'] as $payload) {
            $value = $payload.'.'.hash_hmac('sha256', $payload, $secret);
            self::assertNull($consent->preferences(new Request(cookies: [CookieConsent::COOKIE_NAME => $value])));
        }
    }
}
