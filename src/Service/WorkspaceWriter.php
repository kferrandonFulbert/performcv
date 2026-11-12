<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{CvInstances, CvShares, CvViews, Profiles, Users, TemplatePurchases};
use Doctrine\ORM\EntityManagerInterface;

class WorkspaceWriter
{
    public function __construct(private EntityManagerInterface $em, private ProfilePhoto $photos)
    {
    }

    public function save(object $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function remove(object $entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    public function deleteShare(CvShares $share): void
    {
        $this->em->wrapInTransaction(function () use ($share): void {
            $this->deleteWhere(CvViews::class, 'cv_share_id', $share->getId());
            $this->em->remove($share);
        });
    }

    public function deleteCv(CvInstances $cv): void
    {
        $this->em->wrapInTransaction(function () use ($cv): void {
            $this->deleteCvChildren($cv->getId());
            $this->em->remove($cv);
        });
    }

    public function deleteProfile(Profiles $profile, bool $deletePhoto = true): void
    {
        $this->em->wrapInTransaction(function () use ($profile): void {
            $ids = $this->em->createQueryBuilder()->select('c.id')->from(CvInstances::class, 'c')->where('c.profile_id = :id')->setParameter('id', $profile->getId())->getQuery()->getSingleColumnResult();
            foreach ($ids as $id) {
                $this->deleteCvChildren((int) $id);
            }
            $this->deleteWhere(CvInstances::class, 'profile_id', $profile->getId());
            foreach (SectionCatalog::SECTIONS as $definition) {
                $this->deleteWhere($definition['class'], 'profile_id', $profile->getId());
            }
            $this->em->remove($profile);
        });
        if ($deletePhoto) { $this->photos->delete($profile->getProfilePictureUrl()); }
    }

    public function deleteAccount(Users $user): void
    {
        $photoNames = [];
        $this->em->wrapInTransaction(function () use ($user, &$photoNames): void {
            $this->deleteWhere(TemplatePurchases::class, 'user_id', $user->getId());
            do {
                $profiles = $this->em->getRepository(Profiles::class)->findBy(['user_id' => $user->getId()], ['id' => 'ASC'], 50);
                foreach ($profiles as $profile) {
                    $photoNames[] = $profile->getProfilePictureUrl();
                    $this->deleteProfile($profile, false);
                }
            } while (count($profiles) === 50);
            $this->em->remove($user);
        });
        foreach ($photoNames as $name) { $this->photos->delete($name); }
    }

    private function deleteCvChildren(int $cvId): void
    {
        $this->em->createQuery('DELETE FROM '.CvViews::class.' v WHERE v.cv_share_id IN (SELECT s.id FROM '.CvShares::class.' s WHERE s.cv_instance_id = :id)')->setParameter('id', $cvId)->execute();
        $this->deleteWhere(CvShares::class, 'cv_instance_id', $cvId);
    }

    private function deleteWhere(string $class, string $field, int $id): void
    {
        // Class and column names are internal constants, never request parameters.
        $this->em->createQuery('DELETE FROM '.$class.' e WHERE e.'.$field.' = :id')->setParameter('id', $id)->execute();
    }
}
