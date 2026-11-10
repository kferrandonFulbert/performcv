<?php

namespace App\Entity;

use App\Repository\CvViewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'cv_views')]
#[ORM\Index(name: 'idx_cv_views_cv_share_id', columns: ['cv_share_id'])]
#[ORM\Entity(repositoryClass: CvViewsRepository::class)]
class CvViews
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $cv_share_id = null;

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

    public function getCvShareId(): ?int
    {
        return $this->cv_share_id;
    }

    public function setCvShareId(int $cv_share_id): static
    {
        $this->cv_share_id = $cv_share_id;

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
