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
            $this->deleteWhere(CvViews::class, 'cv_share', $share->getId());
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
            $ids = $this->em->createQueryBuilder()->select('c.id')->from(CvInstances::class, 'c')->where('c.profile = :id')->setParameter('id', $profile->getId())->getQuery()->getSingleColumnResult();
            foreach ($ids as $id) {
                $this->deleteCvChildren((int) $id);
            }
            $this->deleteWhere(CvInstances::class, 'profile', $profile->getId());
            foreach (SectionCatalog::SECTIONS as $definition) {
                $this->deleteWhere($definition['class'], 'profile', $profile->getId());
            }
            $this->em->remove($profile);
        });
        if ($deletePhoto) { $this->photos->delete($profile->getProfilePictureUrl()); }
    }

    public function deleteAccount(Users $user): void
    {
        $photoNames = [];
        $this->em->wrapInTransaction(function () use ($user, &$photoNames): void {
            $this->deleteWhere(TemplatePurchases::class, 'user', $user->getId());

            do {
                $profiles = $this->em->getRepository(Profiles::class)->findBy(['user' => $user->getId()], ['id' => 'ASC'], 50);
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
        // DQL bulk deletion does not synchronize Doctrine's managed objects.
        foreach ($this->em->getUnitOfWork()->getIdentityMap()[CvViews::class] ?? [] as $view) {
            if ($view->getCvShare()?->getCvInstanceId() === $cvId) {
                $this->em->detach($view);
            }
        }
        $this->em->createQuery('DELETE FROM '.CvViews::class.' v WHERE v.cv_share IN (SELECT s.id FROM '.CvShares::class.' s WHERE s.cv_instance = :id)')->setParameter('id', $cvId)->execute();
        $this->deleteWhere(CvShares::class, 'cv_instance', $cvId);
    }

    private function deleteWhere(string $class, string $field, int $id): void
    {
        // Detach only the rows being deleted, leaving other managed objects intact.
        $metadata = $this->em->getClassMetadata($class);
        foreach ($this->em->getUnitOfWork()->getIdentityMap()[$class] ?? [] as $entity) {
            if ($metadata->getFieldValue($entity, $field)?->getId() === $id) {
                $this->em->detach($entity);
            }
        }
        // Class and association names are internal constants, never request parameters.
        $this->em->createQuery('DELETE FROM '.$class.' e WHERE e.'.$field.' = :id')->setParameter('id', $id)->execute();
    }
}
