<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Profiles;
use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Contrôle l’accès propriétaire à un profil à partir du compte connecté.
 */
class ProfileVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'PROFILE_EDIT' && $subject instanceof Profiles;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof Users && $user->getId() !== null && $subject->getUserId() === $user->getId();
    }
}
