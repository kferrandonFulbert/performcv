<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;

final class AuditEventsRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function recent(?int $actorId, int $limit): array
    {
        if ($limit < 1 || $limit > 100 || ($actorId !== null && $actorId < 1)) {
            throw new \InvalidArgumentException('Invalid audit filter.');
        }
        $query = $this->em->getConnection()->createQueryBuilder()
            ->select('id', 'occurred_at', 'actor_id', 'action', 'resource_type', 'resource_id', 'request_id')
            ->from('audit_events')->orderBy('id', 'DESC')->setMaxResults($limit);
        if ($actorId !== null) {
            $query->where('actor_id = :actor')->setParameter('actor', $actorId);
        }

        return $query->executeQuery()->fetchAllAssociative();
    }
}
