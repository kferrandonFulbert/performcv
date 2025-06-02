<?php

namespace App\Entity;

use App\Repository\ProfileSkillsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'profile_skills')]
#[ORM\Index(name: 'profile_id', columns: ['profile_id'])]
#[ORM\Index(name: 'skill_id', columns: ['skill_id'])]
#[ORM\Entity(repositoryClass: ProfileSkillsRepository::class)]
class ProfileSkills
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $profile_id = null;

    #[ORM\Column]
    private ?int $skill_id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $level = null;

    #[ORM\Column(nullable: true)]
    private ?int $years_experience = null;

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

    public function getSkillId(): ?int
    {
        return $this->skill_id;
    }

    public function setSkillId(int $skill_id): static
    {
        $this->skill_id = $skill_id;

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

    public function getYearsExperience(): ?int
    {
        return $this->years_experience;
    }

    public function setYearsExperience(?int $years_experience): static
    {
        $this->years_experience = $years_experience;

        return $this;
    }
}
