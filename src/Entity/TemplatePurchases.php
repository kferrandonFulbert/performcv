<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: \App\Repository\TemplatePurchasesRepository::class)]
#[ORM\Table(name: 'template_purchases')]
#[ORM\UniqueConstraint(name: 'uniq_purchase_user_template', columns: ['user_id', 'template_id'])]
#[ORM\UniqueConstraint(name: 'uniq_purchase_checkout', columns: ['checkout_session_id'])]
#[ORM\UniqueConstraint(name: 'uniq_purchase_payment', columns: ['stripe_payment_id'])]
#[ORM\Index(name: 'idx_purchase_entitlement', columns: ['user_id', 'template_id', 'purchased_at'])]
class TemplatePurchases
{
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;
    #[ORM\Column]
    private int $user_id;
    #[ORM\Column]
    private int $template_id;
    #[ORM\Column]
    private int $amount;
    #[ORM\Column(length: 3)]
    private string $currency = 'eur';
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $checkout_session_id = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripe_payment_id = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $created_at;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $purchased_at = null;

    public function __construct(int $userId, int $templateId, int $amount)
    {
        $this->user_id = $userId;
        $this->template_id = $templateId;
        $this->amount = $amount;
        $this->created_at = new \DateTime();
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUserId(): int
    {
        return $this->user_id;
    }
    public function getTemplateId(): int
    {
        return $this->template_id;
    }
    public function getAmount(): int
    {
        return $this->amount;
    }
    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function getCheckoutSessionId(): ?string
    {
        return $this->checkout_session_id;
    }
    public function setCheckoutSessionId(string $id): static
    {
        $this->checkout_session_id = $id;
        return $this;
    }
    public function getPurchasedAt(): ?\DateTimeInterface
    {
        return $this->purchased_at;
    }
}
