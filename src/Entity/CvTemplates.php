<?php

namespace App\Entity;

use App\Repository\CvTemplatesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'cv_templates')]
#[ORM\Entity(repositoryClass: CvTemplatesRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_cv_template_slug', columns: ['slug'])]
class CvTemplates
{
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $price = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $is_active = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $is_ats_friendly = true;

    public function getSlug(): ?string
    {
        return $this->slug;
    }
    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }
    public function getPrice(): int
    {
        return $this->price;
    }
    public function setPrice(int $price): static
    {
        if ($price < 0) { throw new \InvalidArgumentException('Price cannot be negative.'); }
        $this->price = $price;
        return $this;
    }
    public function isFree(): bool
    {
        return $this->price === 0 && $this->is_premium !== 1;
    }
    public function isActive(): bool
    {
        return $this->is_active;
    }
    public function setIsActive(bool $active): static
    {
        $this->is_active = $active;
        return $this;
    }
    public function isAtsFriendly(): bool
    {
        return $this->is_ats_friendly;
    }
    public function setIsAtsFriendly(bool $ats): static
    {
        $this->is_ats_friendly = $ats;
        return $this;
    }

    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $preview_image_url = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $css_styles = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $html_structure = null;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $is_premium = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPreviewImageUrl(): ?string
    {
        return $this->preview_image_url;
    }

    public function setPreviewImageUrl(?string $preview_image_url): static
    {
        $this->preview_image_url = $preview_image_url;

        return $this;
    }

    public function getCssStyles(): ?string
    {
        return $this->css_styles;
    }

    public function setCssStyles(?string $css_styles): static
    {
        $this->css_styles = $css_styles;

        return $this;
    }

    public function getHtmlStructure(): ?string
    {
        return $this->html_structure;
    }

    public function setHtmlStructure(?string $html_structure): static
    {
        $this->html_structure = $html_structure;

        return $this;
    }

    public function getIsPremium(): ?int
    {
        return $this->is_premium;
    }

    public function setIsPremium(?int $is_premium): static
    {
        $this->is_premium = $is_premium;

        return $this;
    }
}
