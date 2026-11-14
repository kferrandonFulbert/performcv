<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d’une action métier dont les identifiants restent consultables après suppression du compte ou de la ressource.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'audit_events')]
#[ORM\Index(name: 'idx_audit_occurred', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_audit_actor_date', columns: ['actor_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_audit_resource_date', columns: ['resource_type', 'resource_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_audit_request', columns: ['request_id'])]
class AuditEvents
{
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column]
    private ?int $id = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $occurred_at;
    #[ORM\Column(nullable: true)]
    private ?int $actor_id = null;
    #[ORM\Column(length: 64)]
    private string $action;
    #[ORM\Column(length: 32)]
    private string $resource_type;
    #[ORM\Column]
    private int $resource_id;
    #[ORM\Column(length: 32)]
    private string $request_id;

    public function getId(): ?int { return $this->id; }
    public function getOccurredAt(): \DateTimeInterface { return clone $this->occurred_at; }
    public function getActorId(): ?int { return $this->actor_id; }
    public function getAction(): string { return $this->action; }
    public function getResourceType(): string { return $this->resource_type; }
    public function getResourceId(): int { return $this->resource_id; }
    public function getRequestId(): string { return $this->request_id; }
}
