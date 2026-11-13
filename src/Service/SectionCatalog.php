<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Certifications, Educations, Experiences, Languages, ProfileSkills, Projects};

class SectionCatalog
{
    public const SECTIONS = [
        'experiences' => ['class' => Experiences::class, 'label' => 'Expériences', 'title' => 'companyName', 'ordered' => true, 'help' => 'Indiquez votre poste et l’entreprise. Dans la description, partez d’une action que vous avez menée, puis indiquez ce qu’elle a changé. Chiffrez le résultat si vous le connaissez.'],
        'educations' => ['class' => Educations::class, 'label' => 'Formations', 'title' => 'institution', 'ordered' => true, 'help' => 'Reprenez l’intitulé du diplôme et le nom de l’établissement. Dans la description, choisissez un projet ou un enseignement en rapport avec le poste visé.'],
        'certifications' => ['class' => Certifications::class, 'label' => 'Certifications', 'title' => 'name', 'ordered' => false, 'help' => 'Recopiez le nom exact de la certification et de l’organisme qui l’a délivrée. Si elle expire, précisez la date. Ajoutez un lien de vérification public si vous en avez un.'],
        'projects' => ['class' => Projects::class, 'label' => 'Projets', 'title' => 'title', 'ordered' => true, 'help' => 'Donnez un titre qui dit à quoi sert le projet. Précisez votre contribution personnelle, les outils utilisés et le résultat obtenu.'],
        'languages' => ['class' => Languages::class, 'label' => 'Langues', 'title' => 'language', 'ordered' => false, 'help' => 'Nommez la langue et choisissez le niveau qui correspond à ce que vous savez faire aujourd’hui. Par exemple, B2 convient si vous pouvez participer à une discussion et défendre un point de vue.'],
        'skills' => ['class' => ProfileSkills::class, 'label' => 'Compétences', 'title' => 'skillId', 'ordered' => false, 'help' => 'Indiquez une compétence précise par fiche. Par exemple, « Animation de réunions » décrit mieux votre pratique que « Communication ». Choisissez le niveau qui reflète votre autonomie.'],
    ];

    public function get(string $section): array
    {
        return self::SECTIONS[$section] ?? throw new \InvalidArgumentException('Rubrique inconnue.');
    }
}
