<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CookieConsent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CookieController extends AbstractController
{
    #[Route('/cookies', name: 'app_cookies', methods: ['GET', 'POST'])]
    public function preferences(Request $request, CookieConsent $consent): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cookie_preferences', $request->request->getString('_token'))) {
                throw new AccessDeniedHttpException('Le formulaire a expiré. Rechargez la page.');
            }
            $choice = $request->request->getString('choice');
            if (!in_array($choice, ['accept', 'refuse', 'save'], true)) {
                return new Response('Choix de cookies invalide.', Response::HTTP_BAD_REQUEST);
            }
            $analytics = $choice === 'accept' || ($choice === 'save' && $request->request->get('analytics') === '1');
            // No marketing tool is enabled. Do not collect consent for an undefined future use.
            $marketing = false;
            if ($analytics && $request->request->get('adult') !== '1') {
                return $this->render('home/cookies.html.twig', [
                    'preferences' => $consent->preferences($request),
                    'error' => 'Le compteur facultatif est réservé aux personnes majeures qui donnent leur accord. Vous pouvez tout refuser et utiliser le service.',
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            if (!$analytics && $request->hasSession()) {
                foreach (array_keys($request->getSession()->all()) as $key) {
                    if (str_starts_with($key, 'viewed_share_')) {
                        $request->getSession()->remove($key);
                    }
                }
            }
            $response = $this->redirectToRoute('app_cookies', status: Response::HTTP_SEE_OTHER);
            $response->headers->setCookie($consent->cookie($analytics, $marketing, $request->isSecure()));

            return $response;
        }

        return $this->render('home/cookies.html.twig', ['preferences' => $consent->preferences($request)]);
    }
}
