<?php

namespace App\Entity;

use App\Repository\CvSharesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien de partage d’un CV avec ses propres conditions de visibilité, d’expiration et de protection.
 */
#[ORM\Table(name: 'cv_shares')]
#[ORM\UniqueConstraint(name: 'share_token', columns: ['share_token'])]
#[ORM\Index(name: 'idx_cv_shares_cv_instance_id', columns: ['cv_instance_id'])]
#[ORM\Entity(repositoryClass: CvSharesRepository::class)]
class CvShares
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CvInstances::class)]
    #[ORM\JoinColumn(name: 'cv_instance_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_cv_shares_cv_instance_id')]
    private ?CvInstances $cv_instance = null;

    #[ORM\Column(length: 255)]
    private ?string $share_token = null;

    #[ORM\Column(nullable: true, options: ["default" => 1])]
    private ?int $is_public = 1;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $password_protected = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $password_hash = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expires_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ["default" => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCvInstance(): ?CvInstances
    {
        return $this->cv_instance;
    }

    public function getCvInstanceId(): ?int
    {
        return $this->cv_instance?->getId();
    }

    public function setCvInstance(CvInstances $cv_instance): static
    {
        $this->cv_instance = $cv_instance;

        return $this;
    }

    public function getShareToken(): ?string
    {
        return $this->share_token;
    }

    public function setShareToken(string $share_token): static
    {
        $this->share_token = $share_token;

        return $this;
    }

    public function getIsPublic(): ?int
    {
        return $this->is_public;
    }

    public function setIsPublic(?int $is_public): static
    {
        $this->is_public = $is_public;

        return $this;
    }

    public function getPasswordProtected(): ?int
    {
        return $this->password_protected;
    }

    public function setPasswordProtected(?int $password_protected): static
    {
        $this->password_protected = $password_protected;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->password_hash;
    }

    public function setPasswordHash(?string $password_hash): static
    {
        $this->password_hash = $password_hash;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expires_at;
    }

    public function setExpiresAt(?\DateTimeInterface $expires_at): static
    {
        $this->expires_at = $expires_at;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }
}
