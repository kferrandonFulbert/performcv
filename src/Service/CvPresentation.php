<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{CvInstances, CvTemplates, Profiles, Users};
use App\Repository\WorkspaceRepository;
use App\Form\CvType;

class CvPresentation
{
    public function __construct(private WorkspaceRepository $data)
    {
    }

    public function build(Profiles $profile, ?CvInstances $cv = null): array
    {
        $content = $this->data->content($profile->getId(), $cv?->getSectionsVisibility());
        $template = $cv ? $this->data->find(CvTemplates::class, $cv->getTemplateId()) : null;
        $layout = match ($template?->getName()) { 'Atelier' => 'atelier', 'Horizon' => 'horizon', default => 'classic' };
        $color = in_array($cv?->getColorScheme(), CvType::COLORS, true) ? $cv->getColorScheme() : 'ink';
        $font = in_array($cv?->getFontFamily(), CvType::FONTS, true) ? $cv->getFontFamily() : 'sans';

        return ['profile' => $profile, 'owner' => $this->data->find(Users::class, $profile->getUserId()), 'content' => $content,
            'skill_names' => $this->data->skillNames($content['skills'] ?? []), 'layout' => $layout, 'color' => $color, 'font' => $font];
    }
}
