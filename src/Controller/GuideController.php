<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche le guide éditorial associé à chaque route publique de conseil à la candidature.
 */
final class GuideController extends AbstractController
{
    #[Route('/cv-etudiant', name: 'app_cv_etudiant', defaults: ['page' => 'etudiant'], methods: ['GET'])]
    #[Route('/cv-alternance', name: 'app_cv_alternance', defaults: ['page' => 'alternance'], methods: ['GET'])]
    #[Route('/cv-stage', name: 'app_cv_stage', defaults: ['page' => 'stage'], methods: ['GET'])]
    #[Route('/cv-developpeur-web', name: 'app_cv_developpeur_web', defaults: ['page' => 'developpeur-web'], methods: ['GET'])]
    #[Route('/cv-sans-experience', name: 'app_cv_sans_experience', defaults: ['page' => 'sans-experience'], methods: ['GET'])]
    #[Route('/cv-ats', name: 'app_cv_ats', defaults: ['page' => 'ats'], methods: ['GET'])]
    #[Route('/exemple-cv/bts-sio', name: 'app_cv_bts_sio', defaults: ['page' => 'bts-sio'], methods: ['GET'])]
    #[Route('/lettre-motivation/alternance', name: 'app_lettre_alternance', defaults: ['page' => 'lettre-alternance'], methods: ['GET'])]
    public function show(string $page): Response
    {
        return $this->render('guides/'.$page.'.html.twig');
    }
}
