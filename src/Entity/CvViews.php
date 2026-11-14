<?php

namespace App\Entity;

use App\Repository\CvViewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Consultation d’un CV par un lien de partage ; plusieurs affichages peuvent provenir du même visiteur.
 */
#[ORM\Table(name: 'cv_views')]
#[ORM\Index(name: 'idx_cv_views_cv_share_id', columns: ['cv_share_id'])]
#[ORM\Entity(repositoryClass: CvViewsRepository::class)]
class CvViews
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CvShares::class)]
    #[ORM\JoinColumn(name: 'cv_share_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_cv_views_cv_share_id')]
    private ?CvShares $cv_share = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip_address = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $user_agent = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ["default" => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $viewed_at = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $referrer = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCvShare(): ?CvShares
    {
        return $this->cv_share;
    }

    public function getCvShareId(): ?int
    {
        return $this->cv_share?->getId();
    }

    public function setCvShare(CvShares $cv_share): static
    {
        $this->cv_share = $cv_share;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ip_address;
    }

    public function setIpAddress(?string $ip_address): static
    {
        $this->ip_address = $ip_address;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->user_agent;
    }

    public function setUserAgent(?string $user_agent): static
    {
        $this->user_agent = $user_agent;

        return $this;
    }

    public function getViewedAt(): ?\DateTimeInterface
    {
        return $this->viewed_at;
    }

    public function setViewedAt(?\DateTimeInterface $viewed_at): static
    {
        $this->viewed_at = $viewed_at;

        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): static
    {
        $this->referrer = $referrer;

        return $this;
    }
}
