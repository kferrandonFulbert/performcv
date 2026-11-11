<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoTest extends WebTestCase
{
    public function testCanonicalsIgnoreQueryAndUntrustedHost(): void
    {
        $client = static::createClient();
        foreach (['/', '/mentions-legales', '/confidentialite', '/conditions-utilisation', '/cookies'] as $path) {
            $client->request('GET', 'http://untrusted.example'.$path.'?utm_source=test');
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'link[rel="canonical"]');
            self::assertSelectorExists('link[rel="canonical"][href="https://performcv.fr'.$path.'"]');
        }
        foreach (['/connexion', '/inscription', '/absent'] as $path) {
            $client->request('GET', $path);
            self::assertSelectorNotExists('link[rel="canonical"]');
        }
    }

    public function testDiscoveryDocumentsOnlyPublishAllowlistedPages(): void
    {
        $client = static::createClient();
        $client->request('GET', 'http://untrusted.example/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertSame("User-agent: *\nAllow: /\n\nSitemap: https://performcv.fr/sitemap.xml\n", $client->getResponse()->getContent());
        $client->request('GET', 'http://untrusted.example/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($client->getResponse()->getContent());
        self::assertNotFalse($xml);
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        self::assertSame([
            'https://performcv.fr/',
            'https://performcv.fr/mentions-legales',
            'https://performcv.fr/confidentialite',
            'https://performcv.fr/conditions-utilisation',
            'https://performcv.fr/cookies',
        ], array_map(strval(...), $xml->xpath('/s:urlset/s:url/s:loc')));
    }
}
