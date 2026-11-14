<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{CvInstances, CvTemplates, Profiles};
use App\Repository\WorkspaceRepository;
use App\Service\{StripeCheckout, TemplatePayment};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lance le paiement d’un modèle et reçoit les notifications Stripe.
 *
 * La signature est vérifiée avant de transmettre l’événement au service de paiement.
 */
class TemplatePaymentController extends AbstractController
{
    #[Route('/app/cv/{id}/modeles/{templateId}/acheter', name: 'app_template_buy', requirements: ['id' => '\d+', 'templateId' => '\d+'], methods: ['POST'])]
    public function buy(Request $request, int $id, int $templateId, WorkspaceRepository $data, TemplatePayment $payments, #[Autowire('%performcv.public_origin%')] string $origin): Response
    {
        $cv = $data->find(CvInstances::class, $id) ?? throw $this->createNotFoundException();
        $profile = $cv->getProfile() ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('PROFILE_EDIT', $profile);
        if (!$this->isCsrfTokenValid('buy_template_'.$id.'_'.$templateId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $template = $data->find(CvTemplates::class, $templateId) ?? throw $this->createNotFoundException();
        try {
            return $this->redirect($payments->checkout($template, $this->getUser(), $origin.$this->generateUrl('app_cv_edit', ['id' => $id])), 303);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_cv_edit', ['id' => $id], 303);
        } catch (\Stripe\Exception\ApiErrorException|\LogicException|\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            return new Response('Le paiement est temporairement indisponible. Aucun modèle n’a été débloqué.', 503);
        }
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request, StripeCheckout $stripe, TemplatePayment $payments): Response
    {
        if (!$stripe->ready()) { return new Response('', 503); }
        $payload = $request->getContent();
        if (strlen($payload) > 131072) { return new Response('', 413); }
        try {
            $event = $stripe->verify($payload, $request->headers->get('Stripe-Signature', ''));
        } catch (\UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('', 400);
        }
        try {
            $payments->fulfill($event);
        } catch (\UnexpectedValueException $e) {
            return new Response('', 400);
        } catch (\RuntimeException $e) {
            return new Response('', 503);
        }
        return new Response('', 200);
    }
}
