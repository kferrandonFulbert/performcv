<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{CvInstances, CvViews, Profiles, Users};
use App\Form\UnlockType;
use App\Repository\WorkspaceRepository;
use App\Service\{CvPresentation, PublicSeo, ShareAccess, WorkspaceWriter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert l’accueil, les pages légales, les profils publics et les CV partagés.
 *
 * L’accès par lien de partage dépend de sa validité et de son éventuelle protection par mot de passe.
 */
class PublicController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/confidentialite', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('home/privacy.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_legal', methods: ['GET'])]
    public function legal(): Response
    {
        return $this->render('home/legal.html.twig');
    }

    #[Route('/conditions-utilisation', name: 'app_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('home/terms.html.twig');
    }

    #[Route('/p/{slug}', name: 'app_public_profile', requirements: ['slug' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function profile(string $slug, WorkspaceRepository $data, CvPresentation $presentation, PublicSeo $seo): Response
    {
        $profile = $data->publicProfile($slug) ?? throw $this->createNotFoundException();

        return $this->render('cv/public.html.twig', $presentation->build($profile) + ['social_public_url' => $seo->url('app_public_profile', ['slug' => $slug])]);
    }

    #[Route('/s/{token}', name: 'app_public_share', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function share(Request $request, string $token, WorkspaceRepository $data, ShareAccess $access, CvPresentation $presentation, WorkspaceWriter $writer, PasswordHasherFactoryInterface $hashers, PublicSeo $seo): Response
    {
        $share = $data->share($token) ?? throw $this->createNotFoundException();
        if (!$access->active($share)) {
            throw $this->createNotFoundException('Ce lien n’est plus disponible.');
        }
        $cv = $share->getCvInstance() ?? throw $this->createNotFoundException();
        $profile = $cv->getProfile() ?? throw $this->createNotFoundException();
        $session = $request->getSession();
        $key = $access->sessionKey($share);
        if ($access->needsPassword($share) && $session->get($key, 0) < time()) {
            $form = $this->createForm(UnlockType::class)->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($share->getPasswordHash() && $hashers->getPasswordHasher(Users::class)->verify($share->getPasswordHash(), $form->get('password')->getData())) {
                    $session->migrate(true);
                    $session->set($key, time() + 3600);

                    return $this->redirectToRoute('app_public_share', ['token' => $token], 303);
                }
                $form->addError(new FormError('Mot de passe incorrect.'));
            }

            return $this->render('shared/form.html.twig', ['form' => $form, 'title' => 'Ce CV est protégé', 'eyebrow' => 'Accès sur invitation', 'back' => $this->generateUrl('app_home'), 'button' => 'Consulter le CV']);
        }
        // Count successful displays without identifying or tracking the visitor.
        $response = $this->render('cv/public.html.twig', $presentation->build($profile, $cv) + [
            'social_public_url' => $access->needsPassword($share) ? null : $seo->url('app_public_share', ['token' => $token]),
        ]);
        if ($request->isMethod('GET')) {
            $writer->save((new CvViews())->setCvShare($share)->setViewedAt(new \DateTime()));
        }
        $response->headers->set('X-Robots-Tag', 'noarchive');

        return $response;
    }
}
