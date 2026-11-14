<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TemplatePurchases;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Distingue les achats en attente des achats confirmés pour un compte et un modèle.
 */
class TemplatePurchasesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemplatePurchases::class);
    }

    public function hasPurchase(int $userId, int $templateId): bool
    {
        return $this->createQueryBuilder('p')->select('p.id')
            ->where('p.user = :user AND p.template = :template AND p.purchased_at IS NOT NULL')
            ->setParameter('user', $userId)->setParameter('template', $templateId)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult() !== null;
    }

    public function pending(int $userId, int $templateId): ?TemplatePurchases
    {
        return $this->findOneBy(['user' => $userId, 'template' => $templateId, 'purchased_at' => null]);
    }
}
