<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use App\Service\CookieConsent;
use Symfony\Component\Routing\Attribute\Route;

final class CookieController extends AbstractController
{
    #[Route('/cookies', name: 'app_cookies', methods: ['GET', 'POST'])]
    public function preferences(Request $request, CookieConsent $consent): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cookie_preferences', $request->request->getString('_token'))) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Choix non enregistré : formulaire expiré ou invalide.');
            }
            $choice = $request->request->getString('choice');
            if (!in_array($choice, ['accept', 'reject'], true)) {
                throw $this->createNotFoundException();
            }
            $response = $this->redirectToRoute('app_cookies', ['saved' => 1], 303);
            $response->headers->setCookie($consent->cookie($choice === 'accept', false, $request->isSecure()));
            if ($choice === 'reject') {
                foreach ($request->cookies->keys() as $name) {
                    if ($name === '_ga' || str_starts_with($name, '_ga_')) {
                        $response->headers->clearCookie($name, '/');
                        $response->headers->clearCookie($name, '/', $request->getHost());
                    }
                }
            }

            return $response;
        }

        return $this->render('home/cookies.html.twig');
    }
}
