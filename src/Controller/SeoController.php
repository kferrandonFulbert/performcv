<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PublicSeo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Publie le fichier robots.txt et les sitemaps des pages publiques et des guides.
 */
final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(PublicSeo $seo): Response
    {
        // Allow crawlers to request public pages.
        return new Response("User-agent: *\nAllow: /\n\nSitemap: ".$seo->url('app_sitemap')."\n", headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap-pages.xml', name: 'app_sitemap_pages', methods: ['GET'])]
    public function sitemap(PublicSeo $seo): Response
    {
        return $this->render('seo/sitemap.xml.twig', ['urls' => $seo->sitemapUrls()], new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }

    #[Route('/sitemap-conseils-cv.xml', name: 'app_sitemap_guides', methods: ['GET'])]
    public function guides(PublicSeo $seo): Response
    {
        return $this->render('seo/sitemap.xml.twig', ['urls' => $seo->guideSitemapUrls()], new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }
}
