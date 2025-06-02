<?php

namespace App\Entity;

use App\Repository\CvTemplatesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'cv_templates')]
#[ORM\Entity(repositoryClass: CvTemplatesRepository::class)]
class CvTemplates
{
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
