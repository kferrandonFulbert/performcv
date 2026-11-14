<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{CvInstances, CvShares, CvTemplates, CvViews, Profiles, ProfileSkills, Skills, Users};
use App\Service\SectionCatalog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rassemble les profils, rubriques, CV et partages nécessaires aux parcours de l’espace personnel.
 */
class WorkspaceRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $class, int $id): ?object
    {
        return $this->em->find($class, $id);
    }

    public function profiles(int $userId, int $page): array
    {
        return $this->em->getRepository(Profiles::class)->findBy(['user' => $userId], ['id' => 'DESC'], 21, ($page - 1) * 20);
    }

    public function section(string $key, int $profileId): array
    {
        $definition = SectionCatalog::SECTIONS[$key];
        $qb = $this->em->createQueryBuilder()->select('e')->from($definition['class'], 'e')->where('e.profile = :id')->setParameter('id', $profileId)->setMaxResults(100);
        if ($definition['ordered']) {
            $qb->addSelect('CASE WHEN e.order_index IS NULL THEN 1 ELSE 0 END AS HIDDEN missing_order')->orderBy('missing_order', \SortDirection::Ascending)->addOrderBy('e.order_index', \SortDirection::Ascending);
        }

        return $qb->addOrderBy('e.id', \SortDirection::Ascending)->getQuery()->getResult();
    }

    public function content(int $profileId, ?array $visibility = null): array
    {
        $content = [];
        foreach (SectionCatalog::SECTIONS as $key => $definition) {
            // Hidden sections are excluded before querying and rendering.
            if ($visibility !== null && ($visibility[$key] ?? true) !== true) {
                continue;
            }
            $content[$key] = $this->section($key, $profileId);
        }

        return $content;
    }

    public function skillNames(array $entries): array
    {
        $ids = array_values(array_unique(array_map(static fn (ProfileSkills $entry): ?int => $entry->getSkillId(), $entries)));
        if (!$ids) {
            return [];
        }
        $skills = $this->em->getRepository(Skills::class)->findBy(['id' => $ids]);
        $names = [];
        foreach ($skills as $skill) {
            $names[$skill->getId()] = $skill->getName();
        }

        return $names;
    }

    public function skill(string $name): Skills
    {
        $skill = $this->em->getRepository(Skills::class)->findOneBy(['name' => $name]);
        if (!$skill) {
            $skill = (new Skills())->setName($name);
            $this->em->persist($skill);
            $this->em->flush();
        }

        return $skill;
    }

    public function cvs(int $profileId): array
    {
        return $this->em->getRepository(CvInstances::class)->findBy(['profile' => $profileId], ['id' => 'DESC'], 50);
    }

    public function shares(int $cvId): array
    {
        return $this->em->getRepository(CvShares::class)->findBy(['cv_instance' => $cvId], ['id' => 'DESC'], 20);
    }

    public function templates(): array
    {
        $repository = $this->em->getRepository(CvTemplates::class);
        $catalogue = $repository->findBy(['is_active' => true, 'slug' => array_keys(\App\Service\TemplateCatalog::MODELS)], ['price' => 'ASC', 'id' => 'ASC'], 8);
        // Existing installations remain usable until the catalogue is seeded.
        return $catalogue ?: $repository->findBy(['is_active' => true], ['id' => 'ASC'], 30);
    }

    public function share(string $token): ?CvShares
    {
        return $this->em->getRepository(CvShares::class)->findOneBy(['share_token' => $token]);
    }

    public function publicProfile(string $slug): ?Profiles
    {
        return $this->em->getRepository(Profiles::class)->findOneBy(['slug' => $slug, 'is_public' => 1]);
    }

    public function userByEmail(string $email): ?Users
    {
        return $this->em->getRepository(Users::class)->findOneBy(['email' => $email]);
    }

    public function viewCounts(array $shares): array
    {
        if (!$shares) {
            return [];
        }
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(v.cv_share) AS share_id, COUNT(v.id) AS total')->from(CvViews::class, 'v')
            ->where('v.cv_share IN (:ids)')->setParameter('ids', array_map(static fn (CvShares $share): ?int => $share->getId(), $shares))
            ->groupBy('v.cv_share')->getQuery()->getArrayResult();

        return array_column($rows, 'total', 'share_id');
    }
}
