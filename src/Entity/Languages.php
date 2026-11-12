<?php

namespace App\Entity;

use App\Repository\LanguagesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'languages')]
#[ORM\Index(name: 'idx_languages_profile_id', columns: ['profile_id'])]
#[ORM\Entity(repositoryClass: LanguagesRepository::class)]
class Languages
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profiles::class)]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_languages_profile_id')]
    private ?Profiles $profile = null;

    #[ORM\Column(length: 100)]
    private ?string $language = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $level = null;

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

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): static
    {
        $this->level = $level;

        return $this;
    }
}
