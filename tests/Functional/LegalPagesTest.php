<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\CookieConsent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

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
        }
        $client->request('GET', '/mentions-legales');
        self::assertSelectorTextContains('article', '520 628 587');
        self::assertSelectorTextContains('article', 'microentrepreneur');
        self::assertSelectorTextContains('article', 'o2switch');
        $client->request('GET', '/confidentialite');
        self::assertSelectorTextContains('article', '90 jours');
        self::assertSelectorTextContains('article', '30 jours');
        self::assertSelectorTextContains('article', 'mineur');
        self::assertSelectorExists('article a[href="mailto:kferrandon@gmail.com"]');
        $client->request('GET', '/inscription');
        self::assertSelectorTextContains('.form-card .privacy-notice', 'Aucun accord au marketing');
        self::assertSelectorExists('.form-card a[href="/confidentialite"]');
    }

    public function testAcceptPersonalizeAndWithdrawWithoutJavaScript(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cookies');
        self::assertSelectorNotExists('input[type="checkbox"][checked]');
        $client->submit($crawler->selectButton('Tout accepter')->form(['adult' => '1']));
        self::assertResponseRedirects('/cookies', 303);
        $consent = static::getContainer()->get(CookieConsent::class);
        $value = $client->getCookieJar()->get(CookieConsent::COOKIE_NAME)->getValue();
        self::assertSame(['analytics' => true, 'marketing' => false], $consent->preferences(new Request(cookies: [CookieConsent::COOKIE_NAME => $value])));
        $crawler = $client->followRedirect();
        self::assertSelectorCount(1, 'input[type="checkbox"][checked]');
        $client->submit($crawler->selectButton('Enregistrer mes choix')->form(['analytics' => '1', 'adult' => '1']));
        $crawler = $client->followRedirect();
        self::assertSelectorCount(1, 'input[type="checkbox"][checked]');
        $client->submit($crawler->selectButton('Tout refuser')->form());
        $client->followRedirect();
        self::assertSelectorNotExists('input[type="checkbox"][checked]');
        $client->request('GET', '/');
        self::assertSelectorNotExists('script[src*="googletagmanager"]');
    }

    public function testInvalidCsrfCannotChangePreferences(): void
    {
        $client = static::createClient();
        $client->request('POST', '/cookies', ['choice' => 'accept', '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNull($client->getCookieJar()->get(CookieConsent::COOKIE_NAME));
    }

    public function testMinorCanRefuseButCannotEnableOptionalMeasurement(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cookies');
        $client->submit($crawler->selectButton('Tout accepter')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'majeures');
        self::assertNull($client->getCookieJar()->get(CookieConsent::COOKIE_NAME));
        $crawler = $client->getCrawler();
        $client->submit($crawler->selectButton('Tout refuser')->form());
        self::assertResponseRedirects('/cookies', 303);
    }
}
