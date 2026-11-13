<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PublicSeo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(PublicSeo $seo): Response
    {
        // Do not block /s or /app: crawlers must be able to read noindex responses.
        return new Response("User-agent: *\nAllow: /\n\nSitemap: ".$seo->url('app_sitemap')."\nSitemap: ".$seo->url('app_directory_sitemap_index')."\n", headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(PublicSeo $seo): Response
    {
        return $this->render('seo/sitemap.xml.twig', ['urls' => $seo->sitemapUrls()], new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }
}
