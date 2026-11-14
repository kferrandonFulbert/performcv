<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Définit les modèles fournis par l’application et les styles de confiance utilisés pour les rendre.
 */
final class TemplateCatalog
{
    public const MODELS = [
        'classique' => ['name' => 'Classique ATS', 'description' => 'Sobre, une colonne et une lecture linéaire.', 'price' => 0, 'ats' => true],
        'moderne' => ['name' => 'Moderne', 'description' => 'Minimaliste, une colonne et des accents bleus.', 'price' => 0, 'ats' => true],
        'executive' => ['name' => 'Executive', 'description' => 'Une présentation affirmée pour les cadres et dirigeants.', 'price' => 390, 'ats' => true],
        'developer' => ['name' => 'Tech Developer', 'description' => 'Projets et compétences pour les développeurs et profils IT.', 'price' => 390, 'ats' => true],
        'creatif' => ['name' => 'Créatif', 'description' => 'Une composition graphique pour le marketing, la communication et le design.', 'price' => 390, 'ats' => false],
        'elegance' => ['name' => 'Élégance', 'description' => 'Typographie éditoriale et présentation haut de gamme.', 'price' => 390, 'ats' => false],
        'etudiant' => ['name' => 'Étudiant', 'description' => 'Formations et projets en premier pour les stages et alternances.', 'price' => 390, 'ats' => true],
        'timeline' => ['name' => 'Timeline', 'description' => 'Un parcours chronologique visuel, conçu pour la lecture humaine.', 'price' => 390, 'ats' => false],
    ];

    public static function known(?string $slug): bool
    {
        return isset(self::MODELS[$slug ?? '']);
    }
}
