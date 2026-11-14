<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

/**
 * Achat unique d’un modèle par un compte ; seule la date de confirmation ouvre le droit d’utilisation.
 */
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
    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_template_purchases_user_id')]
    private ?Users $user = null;
    #[ORM\ManyToOne(targetEntity: CvTemplates::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_template_purchases_template_id')]
    private ?CvTemplates $template = null;
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

    public function __construct(Users $user, CvTemplates $template, int $amount)
    {
        $this->user = $user;
        $this->template = $template;
        $this->amount = $amount;
        $this->created_at = new \DateTime();
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }
    public function getTemplate(): ?CvTemplates
    {
        return $this->template;
    }

    public function getTemplateId(): ?int
    {
        return $this->template?->getId();
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
