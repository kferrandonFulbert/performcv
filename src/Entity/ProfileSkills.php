<?php

namespace App\Entity;

use App\Repository\ProfileSkillsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compétence appliquée à un profil, avec son niveau et sa durée de pratique.
 */
#[ORM\Table(name: 'profile_skills')]
#[ORM\Index(name: 'idx_profile_skills_profile_id', columns: ['profile_id'])]
#[ORM\Index(name: 'idx_profile_skills_skill_id', columns: ['skill_id'])]
#[ORM\Entity(repositoryClass: ProfileSkillsRepository::class)]
class ProfileSkills
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profiles::class)]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_profile_skills_profile_id')]
    private ?Profiles $profile = null;

    #[ORM\ManyToOne(targetEntity: Skills::class)]
    #[ORM\JoinColumn(name: 'skill_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_profile_skills_skill_id')]
    private ?Skills $skill = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $level = null;

    #[ORM\Column(nullable: true)]
    private ?int $years_experience = null;

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

    public function getSkill(): ?Skills
    {
        return $this->skill;
    }

    public function getSkillId(): ?int
    {
        return $this->skill?->getId();
    }

    public function setSkill(Skills $skill): static
    {
        $this->skill = $skill;

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
