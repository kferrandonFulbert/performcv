<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoTest extends WebTestCase
{
    public function testOnlyAnonymousEditorialResponsesAllowSharedCaching(): void
    {
        $client = static::createClient();
        foreach (['/', '/cv-ats'] as $path) {
            $client->request('GET', $path);
            $response = $client->getResponse();
            self::assertTrue($response->headers->hasCacheControlDirective('public'), $response->headers->get('Cache-Control').' cookies='.count($response->headers->getCookies()).' vary='.implode(',', $response->getVary()));
            self::assertSame(300, $response->getMaxAge());
            self::assertContains('Cookie', $response->getVary());
            self::assertEmpty($response->headers->getCookies());
        }
        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('visitor', 'fixture'));
        $client->request('GET', '/');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $client->request('GET', '/connexion');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    public function testPublicIllustrationsHaveContextAndAreTheOnlySitemapImages(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $sources = $crawler->filter('.public-model-grid img')->each(static function ($image): string {
            self::assertNotEmpty($image->attr('alt'));
            self::assertSame('640', $image->attr('width'));
            self::assertSame('820', $image->attr('height'));
            return $image->attr('src');
        });
        self::assertCount(2, $sources);
        foreach ($sources as $source) {
            self::assertFileExists(dirname(__DIR__, 2).'/public'.$source);
            self::assertNotFalse(simplexml_load_file(dirname(__DIR__, 2).'/public'.$source));
        }
        $client->request('GET', 'http://untrusted.example/sitemap.xml');
        $xml = simplexml_load_string($client->getResponse()->getContent());
        $xml->registerXPathNamespace('image', 'http://www.google.com/schemas/sitemap-image/1.1');
        self::assertSame(array_map(static fn ($source) => 'https://performcv.fr'.$source, $sources), array_map('strval', $xml->xpath('//image:loc')));
    }

    public function testCanonicalsIgnoreQueryAndUntrustedHost(): void
    {
        $client = static::createClient();
        foreach (['/', '/mentions-legales', '/confidentialite', '/conditions-utilisation', '/cookies', '/cv-etudiant', '/cv-alternance', '/cv-stage', '/cv-developpeur-web', '/cv-sans-experience', '/cv-ats', '/exemple-cv/bts-sio', '/lettre-motivation/alternance'] as $path) {
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
        self::assertSame("User-agent: *\nAllow: /\n\nSitemap: https://performcv.fr/sitemap.xml\nSitemap: https://performcv.fr/sitemap-cv.xml\n", $client->getResponse()->getContent());
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
            'https://performcv.fr/cv-etudiant',
            'https://performcv.fr/cv-alternance',
            'https://performcv.fr/cv-stage',
            'https://performcv.fr/cv-developpeur-web',
            'https://performcv.fr/cv-sans-experience',
            'https://performcv.fr/cv-ats',
            'https://performcv.fr/exemple-cv/bts-sio',
            'https://performcv.fr/lettre-motivation/alternance',
            'https://performcv.fr/annuaire-cv',
        ], array_map(strval(...), $xml->xpath('/s:urlset/s:url/s:loc')));
    }

    public function testGuidesHaveDistinctContentExamplesAndWorkingEntryPoints(): void
    {
        $client = static::createClient();
        $paths = ['/cv-etudiant', '/cv-alternance', '/cv-stage', '/cv-developpeur-web', '/cv-sans-experience', '/cv-ats', '/exemple-cv/bts-sio', '/lettre-motivation/alternance'];
        $titles = $descriptions = $examples = [];
        $crawler = $client->request('GET', '/');
        foreach ($paths as $path) {
            self::assertSame(1, $crawler->filter('main a[href="'.$path.'"]')->count());
            self::assertSame(1, $crawler->filter('.advice-menu a[href="'.$path.'"]')->count());
        }
        foreach ($paths as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'h1');
            self::assertSelectorCount(1, 'meta[name="description"]');
            self::assertSelectorExists('article a[href="/inscription"]');
            self::assertSelectorExists('.guide-example');
            $titles[] = $crawler->filter('title')->text();
            $descriptions[] = $crawler->filter('meta[name="description"]')->attr('content');
            $examples[] = $crawler->filter('.guide-example')->text();
        }
        self::assertCount(8, array_unique($titles));
        self::assertCount(8, array_unique($descriptions));
        self::assertCount(8, array_unique($examples));
        $client->request('GET', '/cv-inconnu');
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', '/cv-etudiant');
        self::assertResponseStatusCodeSame(405);
    }
}
