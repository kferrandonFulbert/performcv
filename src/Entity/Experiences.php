<?php

namespace App\Entity;

use App\Repository\ExperiencesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Poste occupé dans une entreprise, terminé ou en cours, rattaché au parcours professionnel du profil.
 */
#[ORM\Table(name: 'experiences')]
#[ORM\Index(name: 'idx_experiences_profile_id', columns: ['profile_id'])]
#[ORM\Entity(repositoryClass: ExperiencesRepository::class)]
class Experiences
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profiles::class)]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_experiences_profile_id')]
    private ?Profiles $profile = null;

    #[ORM\Column(length: 255)]
    private ?string $company_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $end_date = null;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $is_current = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(nullable: true)]
    private ?int $order_index = null;

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

    public function getCompanyName(): ?string
    {
        return $this->company_name;
    }

    public function setCompanyName(string $company_name): static
    {
        $this->company_name = $company_name;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTimeInterface $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEndDate(?\DateTimeInterface $end_date): static
    {
        $this->end_date = $end_date;

        return $this;
    }

    public function getIsCurrent(): ?int
    {
        return $this->is_current;
    }

    public function setIsCurrent(?int $is_current): static
    {
        $this->is_current = $is_current;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getOrderIndex(): ?int
    {
        return $this->order_index;
    }

    public function setOrderIndex(?int $order_index): static
    {
        $this->order_index = $order_index;

        return $this;
    }
}
