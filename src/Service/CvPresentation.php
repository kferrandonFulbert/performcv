<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{CvInstances, CvTemplates, Profiles, Users};
use App\Repository\WorkspaceRepository;
use App\Form\CvType;

class CvPresentation
{
    public function __construct(private WorkspaceRepository $data, private TemplateAccess $access, private ProfilePhoto $photos)
    {
    }

    public function build(Profiles $profile, ?CvInstances $cv = null, bool $preview = false): array
    {
        $content = $this->data->content($profile->getId(), $cv?->getSectionsVisibility());
        $template = $cv ? $this->data->find(CvTemplates::class, $cv->getTemplateId()) : null;
        if ($cv && !$template) { throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Modèle indisponible.'); }
        if ($template && !$preview) { $this->access->require($template, $profile->getUserId()); }
        $theme = TemplateCatalog::known($template?->getSlug()) ? $template->getSlug() : 'classique';
        if ($theme === 'etudiant') {
            $content = array_replace(array_fill_keys(['educations', 'projects', 'skills', 'experiences', 'certifications', 'languages'], []), $content);
        }
        $layout = match ($template?->getName()) { 'Atelier' => 'atelier', 'Horizon' => 'horizon', default => 'classic' };
        $color = in_array($cv?->getColorScheme(), CvType::COLORS, true) ? $cv->getColorScheme() : 'ink';
        $font = in_array($cv?->getFontFamily(), CvType::FONTS, true) ? $cv->getFontFamily() : 'sans';

        return ['profile_photo' => $cv && ($cv->getSectionsVisibility()['photo'] ?? false) === true ? $this->photos->dataUri($profile->getProfilePictureUrl()) : null, 'profile' => $profile, 'owner' => $this->data->find(Users::class, $profile->getUserId()), 'content' => $content,
            'skill_names' => $this->data->skillNames($content['skills'] ?? []), 'layout' => $layout, 'color' => $color, 'font' => $font,
            'theme' => $theme, 'is_preview' => $preview, 'selected_template' => $template];
    }
}
