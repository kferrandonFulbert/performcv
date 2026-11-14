<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoTest extends WebTestCase
{
    public function testSocialCardsUsePageContentAndTrustedUrls(): void
    {
        $client = static::createClient();
        foreach (['/', '/mentions-legales', '/confidentialite', '/cookies', '/conditions-utilisation', '/cv-etudiant', '/cv-alternance', '/cv-stage', '/cv-developpeur-web', '/cv-sans-experience', '/cv-ats', '/exemple-cv/bts-sio', '/lettre-motivation/alternance'] as $path) {
            $crawler = $client->request('GET', 'http://untrusted.example'.$path.'?utm_source=test');
            self::assertResponseIsSuccessful();
            self::assertSame($crawler->filter('title')->text(), $crawler->filter('meta[property="og:title"]')->attr('content'));
            self::assertSame($crawler->filter('meta[name="description"]')->attr('content'), $crawler->filter('meta[property="og:description"]')->attr('content'));
            self::assertSelectorExists('meta[property="og:url"][content="https://performcv.fr'.$path.'"]');
            foreach (['title', 'description', 'image'] as $field) {
                self::assertSelectorCount(1, 'meta[property="og:'.$field.'"]');
                self::assertSame($crawler->filter('meta[property="og:'.$field.'"]')->attr('content'), $crawler->filter('meta[name="twitter:'.$field.'"]')->attr('content'));
            }
            self::assertSelectorExists('meta[name="twitter:card"][content="summary_large_image"]');
            self::assertSelectorExists('meta[property="og:image"][content="https://performcv.fr/images/performcv-social.png"]');
        }
        $client->request('GET', '/connexion');
        self::assertSelectorExists('meta[property="og:url"][content="https://performcv.fr/"]');
        self::assertSame([1200, 630], array_slice(getimagesize(dirname(__DIR__, 2).'/public/images/performcv-social.png'), 0, 2));
    }

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
        $client->request('GET', 'http://untrusted.example/sitemap-pages.xml');
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
        self::assertSame("User-agent: *\nAllow: /\n\nSitemap: https://performcv.fr/sitemap.xml\n", $client->getResponse()->getContent());
        $client->request('GET', 'http://untrusted.example/sitemap-pages.xml');
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

        $home = $client->request('GET', 'https://performcv.fr/');
        $menuUrls = $home->filter('.advice-menu a')->each(static fn ($link) => $link->link()->getUri());
        self::assertCount(8, $menuUrls);
        $client->request('GET', 'http://untrusted.example/sitemap-conseils-cv.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($client->getResponse()->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        self::assertSame($menuUrls, array_map(strval(...), $xml->xpath('/s:urlset/s:url/s:loc')));
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
            self::assertSelectorExists('.guide-cv-example .cv-document');
            self::assertSelectorExists('.guide-rewrite');
            self::assertSelectorExists('.guide-editorial time[datetime="2026-11-14"]');
            self::assertSelectorExists('.guide-editorial a[href="/mentions-legales"]');
            self::assertSelectorExists('.guide-editorial a[href^="https://europass.europa.eu/"]');
            if ($path === '/exemple-cv/bts-sio') {
                self::assertSelectorCount(2, '.guide-cv-example .cv-document');
                self::assertSelectorTextContains('.guide-complete-examples', 'BTS SIO — option SISR');
            }
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
