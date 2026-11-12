<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\CookieConsent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LegalPagesTest extends WebTestCase
{
    public function testPublicInformationAndNoThirdPartyTracking(): void
    {
        $client = static::createClient();
        foreach (['/', '/mentions-legales', '/confidentialite', '/conditions-utilisation', '/cookies', '/inscription', '/connexion'] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('footer a[href="/mentions-legales"]');
            self::assertSelectorExists('footer a[href="/cookies"]');
            self::assertSelectorNotExists('script[src*="googletagmanager"], script[src*="google-analytics"], iframe[src*="googletagmanager"]');
            self::assertSelectorNotExists('script:not([src])');
            $policy = $client->getResponse()->headers->get('Content-Security-Policy');
            self::assertNotNull($policy);
            self::assertStringContainsString("script-src 'self';", $policy);
            self::assertStringNotContainsString("'unsafe-inline'", $policy);
            self::assertStringNotContainsString("'unsafe-eval'", $policy);
            foreach ($client->getCrawler()->filter('script[src]') as $script) {
                self::assertMatchesRegularExpression('~^/(?!/)[^\\s]+$~D', $script->getAttribute('src'));
            }
            foreach ($client->getCrawler()->filter('*') as $element) {
                foreach ($element->attributes as $attribute) {
                    self::assertFalse(str_starts_with(strtolower($attribute->name), 'on'), 'Inline event handler: '.$attribute->name);
                }
            }
        }
        $client->request('GET', '/mentions-legales');
        self::assertSelectorTextContains('article', '520 628 587');
        self::assertSelectorTextContains('article', 'microentrepreneur');
        self::assertSelectorTextContains('article', 'o2switch');
        $client->request('GET', '/confidentialite');
        self::assertSelectorTextContains('article', '90 jours');
        self::assertSelectorTextContains('article', '30 jours');
        self::assertSelectorTextContains('article', 'mineur');
        self::assertSelectorExists('article a[href="mailto:contact@performcv.fr"]');
        $client->request('GET', '/inscription');
        self::assertSelectorTextContains('.form-card .privacy-notice', 'Aucun accord au marketing');
        self::assertSelectorExists('.form-card a[href="/confidentialite"]');
    }

    public function testCookiesPageIsInformationalWithoutConsentForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cookies');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cookie-preferences');
        self::assertSelectorNotExists('input[name="adult"], input[name="analytics"]');
        self::assertSelectorTextContains('article', 'sans cookie de mesure');
        self::assertNull($client->getCookieJar()->get(CookieConsent::COOKIE_NAME));
        $client->request('POST', '/cookies', ['choice' => 'accept']);
        self::assertResponseStatusCodeSame(405);
        self::assertNull($client->getCookieJar()->get(CookieConsent::COOKIE_NAME));
    }
}
