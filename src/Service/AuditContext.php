<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Users;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class AuditContext
{
    public function __construct(private RequestStack $requests, private TokenStorageInterface $tokens)
    {
    }

    public function actorId(): ?int
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof Users ? $user->getId() : null;
    }

    public function requestId(): string
    {
        $request = $this->requests->getMainRequest();
        if ($request === null) {
            return bin2hex(random_bytes(16));
        }
        // Never trust a client-provided header as the correlation identifier.
        if (!$request->attributes->has('_audit_request_id')) {
            $request->attributes->set('_audit_request_id', bin2hex(random_bytes(16)));
        }

        return $request->attributes->get('_audit_request_id');
    }
}
