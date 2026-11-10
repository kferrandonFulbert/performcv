<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Certifications, Educations, Experiences, Languages, ProfileSkills, Projects};

class SectionCatalog
{
    public const SECTIONS = [
        'experiences' => ['class' => Experiences::class, 'label' => 'Expériences', 'title' => 'companyName', 'ordered' => true],
        'educations' => ['class' => Educations::class, 'label' => 'Formations', 'title' => 'institution', 'ordered' => true],
        'certifications' => ['class' => Certifications::class, 'label' => 'Certifications', 'title' => 'name', 'ordered' => false],
        'projects' => ['class' => Projects::class, 'label' => 'Projets', 'title' => 'title', 'ordered' => true],
        'languages' => ['class' => Languages::class, 'label' => 'Langues', 'title' => 'language', 'ordered' => false],
        'skills' => ['class' => ProfileSkills::class, 'label' => 'Compétences', 'title' => 'skillId', 'ordered' => false],
    ];

    public function get(string $section): array
    {
        return self::SECTIONS[$section] ?? throw new \InvalidArgumentException('Rubrique inconnue.');
    }
}
