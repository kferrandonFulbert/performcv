<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Users, Profiles, CvInstances, CvShares};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final class AuditTrail
{
    private const ACTIONS = [
        'account.created' => 'account', 'account.updated' => 'account',
        'account.password_changed' => 'account', 'account.deleted' => 'account',
        'profile.created' => 'profile', 'profile.updated' => 'profile', 'profile.deleted' => 'profile',
        'cv.created' => 'cv', 'cv.updated' => 'cv', 'cv.deleted' => 'cv',
        'cv.directory_published' => 'cv', 'cv.directory_withdrawn' => 'cv',
        'share.created' => 'share', 'share.updated' => 'share', 'share.revoked' => 'share',
        'entry.created' => 'entry', 'entry.updated' => 'entry', 'entry.deleted' => 'entry',
        'purchase.created' => 'purchase', 'purchase.checkout_created' => 'purchase', 'purchase.confirmed' => 'purchase',
    ];

    public function __construct(private EntityManagerInterface $em, private AuditContext $context)
    {
    }

    public function resourceType(object $entity): ?string
    {
        foreach ([Users::class => 'account', Profiles::class => 'profile', CvInstances::class => 'cv', CvShares::class => 'share'] as $class => $type) {
            if ($entity instanceof $class) { return $type; }
        }
        foreach (SectionCatalog::SECTIONS as $definition) {
            if ($entity instanceof $definition['class']) {
                // Distinguish entry tables whose generated identifiers may overlap.
                return $definition['class'];
            }
        }

        return null;
    }

    public function append(string $action, string $resourceType, int $resourceId, ?int $actorId): void
    {
        $expected = self::ACTIONS[$action] ?? null;
        $entryTypes = array_column(SectionCatalog::SECTIONS, 'class');
        if ($expected === null || ($expected === 'entry' ? !in_array($resourceType, $entryTypes, true) : $expected !== $resourceType)
            || $resourceId < 1 || ($actorId !== null && $actorId < 1)) {
            throw new \InvalidArgumentException('Invalid audit event.');
        }
        if (!$this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('Audit events require the business transaction.');
        }
        // No free-form metadata, request content, URL, secret or personal content.
        $this->em->getConnection()->insert('audit_events', [
            'occurred_at' => new \DateTime('now', new \DateTimeZone('UTC')),
            'actor_id' => $actorId, 'action' => $action, 'resource_type' => $resourceType,
            'resource_id' => $resourceId, 'request_id' => $this->context->requestId(),
        ], ['occurred_at' => Types::DATETIME_MUTABLE]);
    }
}
