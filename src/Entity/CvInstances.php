<?php

namespace App\Entity;

use App\Repository\CvInstancesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'cv_instances')]
#[ORM\Index(name: 'profile_id', columns: ['profile_id'])]
#[ORM\Index(name: 'template_id', columns: ['template_id'])]
#[ORM\Entity(repositoryClass: CvInstancesRepository::class)]
class CvInstances
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $profile_id = null;

    #[ORM\Column]
    private ?int $template_id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $custom_styles = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $sections_visibility = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color_scheme = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $font_family = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ["default" => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ["default" => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $updated_at = null;

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

    public function getTemplateId(): ?int
    {
        return $this->template_id;
    }

    public function setTemplateId(int $template_id): static
    {
        $this->template_id = $template_id;

        return $this;
    }

    public function getCustomStyles(): ?string
    {
        return $this->custom_styles;
    }

    public function setCustomStyles(?string $custom_styles): static
    {
        $this->custom_styles = $custom_styles;

        return $this;
    }

    public function getSectionsVisibility(): ?array
    {
        return $this->sections_visibility;
    }

    public function setSectionsVisibility(?array $sections_visibility): static
    {
        $this->sections_visibility = $sections_visibility;

        return $this;
    }

    public function getColorScheme(): ?string
    {
        return $this->color_scheme;
    }

    public function setColorScheme(?string $color_scheme): static
    {
        $this->color_scheme = $color_scheme;

        return $this;
    }

    public function getFontFamily(): ?string
    {
        return $this->font_family;
    }

    public function setFontFamily(?string $font_family): static
    {
        $this->font_family = $font_family;

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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}
