<?php

namespace App\Entity;

use App\Repository\CvInstancesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'cv_instances')]
#[ORM\Index(name: 'idx_cv_instances_profile_id', columns: ['profile_id'])]
#[ORM\Index(name: 'idx_cv_instances_template_id', columns: ['template_id'])]
#[ORM\Index(name: 'idx_cv_directory', columns: ['directory_listed', 'id'])]
#[ORM\Entity(repositoryClass: CvInstancesRepository::class)]
class CvInstances
{
    #[ORM\Column(options: ['default' => false])]
    private bool $directory_listed = false;

    public function isDirectoryListed(): bool { return $this->directory_listed; }

    public function setDirectoryListed(bool $listed): static
    {
        $this->directory_listed = $listed;
        return $this;
    }

    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profiles::class)]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_cv_instances_profile_id')]
    private ?Profiles $profile = null;

    #[ORM\ManyToOne(targetEntity: CvTemplates::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT', foreignKeyName: 'fk_cv_instances_template_id')]
    private ?CvTemplates $template = null;

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

    public function getTemplate(): ?CvTemplates
    {
        return $this->template;
    }

    public function getTemplateId(): ?int
    {
        return $this->template?->getId();
    }

    public function setTemplate(CvTemplates $template): static
    {
        $this->template = $template;

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
