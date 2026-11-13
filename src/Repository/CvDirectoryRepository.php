<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CvShares;
use App\Service\DirectorySearch;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;

final class CvDirectoryRepository
{
    public const PAGE_SIZE = 20;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    private function base(bool $listed = true): QueryBuilder
    {
        $query = $this->em->getConnection()->createQueryBuilder()->from('cv_instances', 'cv')
            ->innerJoin('cv', 'profiles', 'p', 'p.id = cv.profile_id')
            ->innerJoin('p', 'users', 'u', 'u.id = p.user_id')
            ->innerJoin('cv', 'cv_templates', 't', 't.id = cv.template_id')
            ->where('EXISTS (SELECT 1 FROM cv_shares s WHERE '.$this->shareCondition().')')
            ->andWhere("((t.price = 0 AND (t.is_premium IS NULL OR t.is_premium <> 1)) OR EXISTS (SELECT 1 FROM template_purchases tp WHERE tp.user_id = u.id AND tp.template_id = t.id AND tp.purchased_at IS NOT NULL))")
            ->andWhere("TRIM(COALESCE(u.first_name, '')) <> '' AND TRIM(COALESCE(u.last_name, '')) <> '' AND TRIM(COALESCE(p.title, '')) <> ''")
            ->setParameter('now', gmdate('Y-m-d H:i:s'));
        if ($listed) { $query->andWhere('cv.directory_listed = 1'); }
        return $query;
    }

    public function canPublish(int $cvId): bool
    {
        return $this->base(false)->select('cv.id')->andWhere('cv.id = :id')->setParameter('id', $cvId)->executeQuery()->fetchOne() !== false;
    }

    private function shareCondition(): string
    {
        return 's.cv_instance_id = cv.id AND s.is_public = 1 AND s.password_protected = 0 AND s.password_hash IS NULL AND (s.expires_at IS NULL OR s.expires_at > :now)';
    }

    public function publishedShare(int $cvId): ?CvShares
    {
        $id = $this->base()->select('(SELECT MIN(s.id) FROM cv_shares s WHERE '.$this->shareCondition().') AS share_id')->andWhere('cv.id = :id')->setParameter('id', $cvId)->executeQuery()->fetchOne();
        return $id === false ? null : $this->em->find(CvShares::class, (int) $id);
    }

    public function count(): int
    {
        return (int) $this->base()->select('COUNT(cv.id)')->executeQuery()->fetchOne();
    }

    public function sitemapIds(int $page): array
    {
        return $this->base()->select('cv.id')->orderBy('cv.id')->setFirstResult(($page - 1) * 1000)->setMaxResults(1000)->executeQuery()->fetchFirstColumn();
    }

    private function fold(string $expression): string
    {
        $sql = 'COALESCE('.$expression.", '')";
        foreach (DirectorySearch::ACCENTS as $from => $to) {
            $sql = "REPLACE($sql, '$from', '$to')";
        }
        return 'LOWER('.$sql.')';
    }

    private function visible(string $section): string
    {
        if ($this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
            return "COALESCE(json_type(cv.sections_visibility, '$.$section'), 'null') IN ('null', 'true')";
        }
        $value = "JSON_EXTRACT(cv.sections_visibility, '$.$section')";
        return "($value IS NULL OR JSON_TYPE($value) = 'NULL' OR (JSON_TYPE($value) = 'BOOLEAN' AND JSON_UNQUOTE($value) = 'true'))";
    }

    private function section(string $section, string $table, string $extra = '1 = 1'): string
    {
        return '('.$this->visible($section)." AND EXISTS (SELECT 1 FROM $table e WHERE e.profile_id = p.id AND ($extra)))";
    }

    public function search(string $search, int $page): array
    {
        if ($page < 1 || $page > 10000) { throw new \InvalidArgumentException('Page invalide.'); }
        $terms = DirectorySearch::terms($search);
        $query = $this->base();
        $platform = $this->em->getConnection()->getDatabasePlatform();
        $name = $this->fold($platform->getConcatExpression("COALESCE(u.first_name, '')", "' '", "COALESCE(u.last_name, '')"));
        $reverse = $this->fold($platform->getConcatExpression("COALESCE(u.last_name, '')", "' '", "COALESCE(u.first_name, '')"));
        $relevance = [];
        foreach ($terms as $index => $term) {
            $pattern = ':term'.$index;
            $query->setParameter('term'.$index, DirectorySearch::pattern($term));
            $like = fn (string $field): string => $this->fold($field)." LIKE $pattern ESCAPE '!'";
            $nameMatch = "$name LIKE $pattern ESCAPE '!'";
            $titleMatch = $like('p.title');
            $skills = $this->section('skills', 'profile_skills', 'EXISTS (SELECT 1 FROM skills sk WHERE sk.id = e.skill_id AND '.$like('sk.name').')');
            $matches = [$nameMatch, $titleMatch, $like('p.summary'), $like('p.location'), $skills];
            foreach ([
                'experiences' => ['experiences', ['position', 'company_name', 'description']],
                'educations' => ['educations', ['institution', 'degree', 'field_of_study', 'description']],
                'projects' => ['projects', ['title', 'description', 'technologies_used']],
                'certifications' => ['certifications', ['name', 'issuing_organization']],
                'languages' => ['languages', ['language']],
            ] as $section => [$table, $fields]) {
                $matches[] = $this->section($section, $table, implode(' OR ', array_map(fn (string $field): string => $like('e.'.$field), $fields)));
            }
            $query->andWhere('('.implode(' OR ', $matches).')');
            $relevance[] = "(CASE WHEN $nameMatch THEN 1.0 WHEN $titleMatch THEN 0.9 WHEN $skills THEN 0.8 ELSE 0.5 END)";
        }
        $length = $platform instanceof SQLitePlatform ? 'LENGTH' : 'CHAR_LENGTH';
        $complete = "(CASE WHEN TRIM(COALESCE(p.title, '')) <> '' THEN 15 ELSE 0 END + CASE WHEN $length(TRIM(COALESCE(p.summary, ''))) >= 80 THEN 20 ELSE 0 END";
        $complete .= ' + CASE WHEN '.$this->section('experiences', 'experiences').' OR '.$this->section('educations', 'educations').' OR '.$this->section('projects', 'projects').' THEN 25 ELSE 0 END';
        $complete .= ' + CASE WHEN '.$this->section('skills', 'profile_skills').' THEN 25 ELSE 0 END';
        $complete .= " + CASE WHEN TRIM(COALESCE(p.location, '')) <> '' THEN 10 ELSE 0 END + CASE WHEN ".$this->section('languages', 'languages').' THEN 5 ELSE 0 END)';
        $freshness = "CASE WHEN COALESCE(cv.updated_at, cv.created_at) >= :recent THEN 10 WHEN COALESCE(cv.updated_at, cv.created_at) >= :older THEN 5 ELSE 0 END";
        $query->setParameter('recent', gmdate('Y-m-d H:i:s', time() - 30 * 86400))->setParameter('older', gmdate('Y-m-d H:i:s', time() - 90 * 86400));
        $score = ($relevance ? '60.0 * ('.implode(' + ', $relevance).') / '.count($terms).' + ' : '')."0.25 * $complete + ($freshness) + CASE WHEN t.price > 0 THEN 5 ELSE 0 END";
        $exact = '0';
        if ($terms) {
            $query->setParameter('exact', DirectorySearch::normalize($search));
            $exact = "CASE WHEN $name = :exact OR $reverse = :exact THEN 1 ELSE 0 END";
        }
        $rows = $query->select('cv.id', 'p.title', 'p.summary', 'p.location', 'u.first_name', 'u.last_name', "$score AS ranking_score", "$exact AS exact_name")
            ->orderBy('exact_name', 'DESC')->addOrderBy('ranking_score', 'DESC')->addOrderBy('cv.id', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE + 1)->executeQuery()->fetchAllAssociative();
        return ['items' => array_slice($rows, 0, self::PAGE_SIZE), 'next' => count($rows) > self::PAGE_SIZE];
    }
}
