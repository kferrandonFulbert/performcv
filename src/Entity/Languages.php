<?php

namespace App\Entity;

use App\Repository\LanguagesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'languages')]
#[ORM\Index(name: 'profile_id', columns: ['profile_id'])]
#[ORM\Entity(repositoryClass: LanguagesRepository::class)]
class Languages
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $profile_id = null;

    #[ORM\Column(length: 100)]
    private ?string $language = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $level = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfileId(): ?int
    {
        return $this->profile_id;
    }

    public function setProfileId(int $profile_id): static
    {
        $this->profile_id = $profile_id;

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
