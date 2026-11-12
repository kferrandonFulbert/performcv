<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CookieController extends AbstractController
{
    #[Route('/cookies', name: 'app_cookies', methods: ['GET'])]
    public function preferences(): Response
    {
        return $this->render('home/cookies.html.twig');
    }
}
