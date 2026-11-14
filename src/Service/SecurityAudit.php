<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consigne les événements de sécurité avec leur acteur et leur identifiant de corrélation.
 */
final class SecurityAudit
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.audit_security')] private LoggerInterface $logger,
        private AuditContext $context,
    ) {
    }

    public function record(string $action, ?int $actorId): void
    {
        if (!in_array($action, ['auth.login_succeeded', 'auth.login_failed', 'auth.logout', 'access.denied', 'access.rate_limited', 'audit.read', 'audit.purged', 'audit.purge_simulated', 'photos.clean_simulated', 'photos.cleaned', 'photos.clean_failed'], true)) {
            throw new \InvalidArgumentException('Invalid security audit event.');
        }
        try {
            $this->logger->info($action, ['actor_id' => $actorId, 'request_id' => $this->context->requestId()]);
        } catch (\Throwable) {
            // Logging must not prevent logout or change an authentication/access decision.
            // A fixed fallback contains neither submitted credentials nor exception details.
            error_log('performcv: security audit logging unavailable');
        }
    }
}
