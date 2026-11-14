<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CvShares;

/**
 * Évalue la visibilité, l’expiration et la protection par mot de passe d’un lien de partage.
 */
class ShareAccess
{
    public function active(CvShares $share, ?\DateTimeInterface $now = null): bool
    {
        return $share->getIsPublic() === 1 && ($share->getExpiresAt() === null || $share->getExpiresAt() > ($now ?? new \DateTimeImmutable()));
    }

    public function needsPassword(CvShares $share): bool
    {
        return $share->getPasswordProtected() !== 0;
    }

    public function sessionKey(CvShares $share): string
    {
        return 'share_access_'.hash('sha256', $share->getShareToken().'|'.$share->getPasswordHash());
    }
}
