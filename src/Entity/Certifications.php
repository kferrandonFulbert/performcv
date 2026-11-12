<?php

namespace App\Entity;

use App\Repository\CertificationsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'certifications')]
#[ORM\Index(name: 'idx_certifications_profile_id', columns: ['profile_id'])]
#[ORM\Entity(repositoryClass: CertificationsRepository::class)]
class Certifications
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profiles::class)]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_certifications_profile_id')]
    private ?Profiles $profile = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $issuing_organization = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $issue_date = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiration_date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $credential_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $credential_url = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): ?Profiles
    {
        return $this->profile;
    }

    public function getProfileId(): ?int
    {
        return $this->profile?->getId();
    }

    public function setProfile(Profiles $profile): static
    {
        $this->profile = $profile;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getIssuingOrganization(): ?string
    {
        return $this->issuing_organization;
    }

    public function setIssuingOrganization(?string $issuing_organization): static
    {
        $this->issuing_organization = $issuing_organization;

        return $this;
    }

    public function getIssueDate(): ?\DateTimeInterface
    {
        return $this->issue_date;
    }

    public function setIssueDate(?\DateTimeInterface $issue_date): static
    {
        $this->issue_date = $issue_date;

        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expiration_date;
    }

    public function setExpirationDate(?\DateTimeInterface $expiration_date): static
    {
        $this->expiration_date = $expiration_date;

        return $this;
    }

    public function getCredentialId(): ?string
    {
        return $this->credential_id;
    }

    public function setCredentialId(?string $credential_id): static
    {
        $this->credential_id = $credential_id;

        return $this;
    }

    public function getCredentialUrl(): ?string
    {
        return $this->credential_url;
    }

    public function setCredentialUrl(?string $credential_url): static
    {
        $this->credential_url = $credential_url;

        return $this;
    }
}
