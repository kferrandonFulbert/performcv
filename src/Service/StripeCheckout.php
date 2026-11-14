<?php

declare(strict_types=1);

namespace App\Service;

use Stripe\{StripeClient, Webhook};

/**
 * Crée et consulte les sessions de paiement Stripe et vérifie la signature des notifications reçues.
 */
class StripeCheckout
{
    public function __construct(private string $secretKey, private string $webhookSecret) {}

    public function ready(): bool
    {
        return $this->secretKey !== '' && $this->webhookSecret !== '';
    }

    public function create(array $parameters, string $idempotencyKey): array
    {
        return (new StripeClient($this->secretKey))->checkout->sessions->create($parameters, ['idempotency_key' => $idempotencyKey])->toArray();
    }

    public function retrieve(string $sessionId): array
    {
        return (new StripeClient($this->secretKey))->checkout->sessions->retrieve($sessionId)->toArray();
    }

    public function verify(string $payload, string $signature): array
    {
        if (!$this->ready()) { throw new \LogicException('Stripe is not configured.'); }
        $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret)->toArray();
        $live = str_starts_with($this->secretKey, 'sk_live_') || str_starts_with($this->secretKey, 'rk_live_');
        if (($event['livemode'] ?? null) !== $live) { throw new \UnexpectedValueException('Payment mode mismatch.'); }
        return $event;
    }
}
