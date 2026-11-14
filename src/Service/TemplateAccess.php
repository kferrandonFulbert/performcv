<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CvTemplates;
use App\Repository\TemplatePurchasesRepository;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Vérifie le droit d’utiliser un modèle gratuit ou dont l’achat a été confirmé pour le compte.
 */
class TemplateAccess
{
    public function __construct(private TemplatePurchasesRepository $purchases)
    {
    }

    public function allowed(CvTemplates $template, int $userId): bool
    {
        return $template->isFree() || $this->purchases->hasPurchase($userId, $template->getId());
    }

    public function require(CvTemplates $template, int $userId): void
    {
        if (!$this->allowed($template, $userId)) {
            throw new AccessDeniedException('Ce modèle doit être acheté avant utilisation.');
        }
    }
}
