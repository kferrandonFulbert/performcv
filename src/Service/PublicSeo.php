<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Construit les URL canoniques et les entrées des sitemaps à partir de l’origine de confiance.
 */
final class PublicSeo
{
    private const ROUTES = [
        'app_home', 'app_legal', 'app_privacy', 'app_terms', 'app_cookies',
    ];

    public const GUIDE_LINKS = [
        'app_cv_etudiant' => 'CV étudiant',
        'app_cv_alternance' => 'CV alternance',
        'app_cv_stage' => 'CV stage',
        'app_cv_developpeur_web' => 'CV développeur web',
        'app_cv_sans_experience' => 'CV sans expérience',
        'app_cv_ats' => 'CV ATS',
        'app_cv_bts_sio' => 'Exemple CV BTS SIO',
        'app_lettre_alternance' => 'Lettre de motivation alternance',
    ];

    public function __construct(private UrlGeneratorInterface $urls, private string $origin)
    {
        if (!preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~iD', $origin)) {
            throw new \InvalidArgumentException('The public origin must be an HTTPS origin without a path.');
        }
    }

    public function canonical(?string $route): ?string
    {
        return ($route === 'app_cv_directory' || isset(self::GUIDE_LINKS[$route]) || in_array($route, self::ROUTES, true)) ? $this->url($route) : null;
    }

    public function sitemapUrls(): array
    {
        return array_map($this->url(...), self::ROUTES);
    }

    public function guideSitemapUrls(): array
    {
        return array_map($this->url(...), array_keys(self::GUIDE_LINKS));
    }

    public function sitemapImages(string $pageUrl): array
    {
        // Editorial illustrations only: never add uploaded portraits or CV tokens.
        return $pageUrl === $this->url('app_home') ? [
            $this->origin.'/images/exemple-cv-classique-ats.svg',
            $this->origin.'/images/exemple-cv-moderne.svg',
        ] : [];
    }

    public function url(string $route, array $parameters = []): string
    {
        // Only the path comes from the router: never trust the request's host or scheme.
        return $this->origin.$this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function socialImageUrl(): string
    {
        return $this->origin.'/images/performcv-social.png';
    }

    /** Convert an escaped Twig text block back to text before attribute escaping. */
    public function plainText(string $text): string
    {
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
