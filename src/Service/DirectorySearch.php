<?php

declare(strict_types=1);

namespace App\Service;

final class DirectorySearch
{
    // Deliberately identical folding on MySQL and SQLite, including upper-case accents.
    public const ACCENTS = [
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
        'à'=>'a','â'=>'a','ä'=>'a','À'=>'a','Â'=>'a','Ä'=>'a','ç'=>'c','Ç'=>'c',
        'î'=>'i','ï'=>'i','Î'=>'i','Ï'=>'i','ô'=>'o','ö'=>'o','Ô'=>'o','Ö'=>'o',
        'ù'=>'u','û'=>'u','ü'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','œ'=>'oe','Œ'=>'oe',
    ];

    public static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(strtr($value, self::ACCENTS))));
    }

    public static function terms(string $query): array
    {
        if (!mb_check_encoding($query, 'UTF-8')) { throw new \InvalidArgumentException('Recherche invalide.'); }
        if (mb_strlen($query) > 80) { throw new \InvalidArgumentException('La recherche est limitée à 80 caractères.'); }
        $normalized = self::normalize($query);
        $terms = $normalized === '' ? [] : array_values(array_unique(explode(' ', $normalized)));
        if (count($terms) > 6) { throw new \InvalidArgumentException('Saisissez au maximum six mots.'); }
        return $terms;
    }

    public static function pattern(string $term): string
    {
        return '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }
}
