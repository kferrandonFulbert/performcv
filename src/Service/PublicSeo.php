<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicSeo
{
    private const ROUTES = [
        'app_home', 'app_legal', 'app_privacy', 'app_terms', 'app_cookies',
        'app_cv_etudiant', 'app_cv_alternance', 'app_cv_stage', 'app_cv_developpeur_web',
        'app_cv_sans_experience', 'app_cv_ats', 'app_cv_bts_sio', 'app_lettre_alternance',
        'app_cv_directory',
    ];

    public function __construct(private UrlGeneratorInterface $urls, private string $origin)
    {
        if (!preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~iD', $origin)) {
            throw new \InvalidArgumentException('The public origin must be an HTTPS origin without a path.');
        }
    }

    public function canonical(?string $route): ?string
    {
        return in_array($route, self::ROUTES, true) ? $this->url($route) : null;
    }

    public function sitemapUrls(): array
    {
        return array_map($this->url(...), self::ROUTES);
    }

    public function url(string $route, array $parameters = []): string
    {
        // Only the path comes from the router: never trust the request's host or scheme.
        return $this->origin.$this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
