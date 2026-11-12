<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{CvTemplates, TemplatePurchases, Users};
use App\Repository\TemplatePurchasesRepository;
use Doctrine\ORM\EntityManagerInterface;

class TemplatePayment
{
    public function __construct(
        private EntityManagerInterface $em,
        private StripeCheckout $stripe,
        private TemplateAccess $access,
        private TemplatePurchasesRepository $purchases,
    ) {
    }

    public function checkout(CvTemplates $template, Users $user, string $returnUrl): string
    {
        if (!$this->stripe->ready()) { throw new \LogicException('Les achats ne sont pas encore disponibles.'); }
        if (!$template->isActive() || !TemplateCatalog::known($template->getSlug()) || $template->getPrice() !== 390 || $this->access->allowed($template, $user->getId())) {
            throw new \DomainException('Ce modèle ne nécessite pas de nouvel achat.');
        }
        $pending = $this->purchases->pending($user->getId(), $template->getId());
        if ($pending?->getCheckoutSessionId()) {
            $session = $this->stripe->retrieve($pending->getCheckoutSessionId());
            if (($session['status'] ?? '') === 'open') { return $this->checkoutUrl($session); }
            if (($session['status'] ?? '') === 'complete') {
                throw new \DomainException('Le paiement est en cours de confirmation. Actualisez dans quelques instants.');
            }
            if (($session['status'] ?? '') !== 'expired') { throw new \UnexpectedValueException('Unexpected Checkout state.'); }
        }
        $purchase = $pending ?? new TemplatePurchases($user, $template, $template->getPrice());
        $this->em->persist($purchase);
        $this->em->flush();
        $session = $this->stripe->create([
            'mode' => 'payment', 'payment_method_types' => ['card'],
            'client_reference_id' => (string) $purchase->getId(),
            'metadata' => ['purchase_id' => (string) $purchase->getId(), 'user_id' => (string) $user->getId(), 'template_id' => (string) $template->getId()],
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $purchase->getAmount(), 'product_data' => ['name' => 'Modèle PerformCV — '.$template->getName()]]]],
            'success_url' => $returnUrl.'?payment=confirmation', 'cancel_url' => $returnUrl.'?payment=cancelled',
        ], 'performcv-template-'.$purchase->getId().'-'.($purchase->getCheckoutSessionId() ?? 'initial'));
        $purchase->setCheckoutSessionId($session['id']);
        $this->em->flush();
        return $this->checkoutUrl($session);
    }

    public function fulfill(array $event): void
    {
        if (!in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) { return; }
        $session = $event['data']['object'] ?? [];
        if (($session['payment_status'] ?? '') !== 'paid') { return; }
        $id = $session['metadata']['purchase_id'] ?? '';
        if (!is_string($id) || !ctype_digit($id)) { throw new \UnexpectedValueException('Unknown purchase.'); }
        $purchase = $this->em->find(TemplatePurchases::class, (int) $id);
        if (!$purchase) { return; } // Account may have been deleted since Checkout.
        if (!$purchase->getCheckoutSessionId()) { throw new \RuntimeException('Checkout registration pending.'); }
        if (($session['id'] ?? null) !== $purchase->getCheckoutSessionId()
            || ($session['client_reference_id'] ?? null) !== (string) $purchase->getId()
            || ($session['metadata']['user_id'] ?? null) !== (string) $purchase->getUserId()
            || ($session['metadata']['template_id'] ?? null) !== (string) $purchase->getTemplateId()
            || ($session['amount_total'] ?? null) !== $purchase->getAmount()
            || ($session['currency'] ?? null) !== $purchase->getCurrency()
            || ($session['mode'] ?? null) !== 'payment'
            || !is_string($session['payment_intent'] ?? null) || !str_starts_with($session['payment_intent'], 'pi_')) {
            throw new \UnexpectedValueException('Payment does not match the purchase.');
        }
        if (!$this->em->find(Users::class, $purchase->getUserId()) || !$this->em->find(CvTemplates::class, $purchase->getTemplateId())) { return; }
        // Atomic conditional update makes concurrent delivery and replay idempotent.
        $this->em->getConnection()->executeStatement(
            'UPDATE template_purchases SET stripe_payment_id = ?, purchased_at = ? WHERE id = ? AND purchased_at IS NULL',
            [$session['payment_intent'], new \DateTime(), $purchase->getId()],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, \Doctrine\DBAL\ParameterType::INTEGER],
        );
    }

    private function checkoutUrl(array $session): string
    {
        $url = $session['url'] ?? '';
        if (!is_string($url) || !str_starts_with($url, 'https://checkout.stripe.com/')) {
            throw new \UnexpectedValueException('Unexpected Checkout URL.');
        }
        return $url;
    }
}
