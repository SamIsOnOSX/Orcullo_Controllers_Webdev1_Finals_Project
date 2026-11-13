<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Runs on every sign-in attempt. Rejects accounts the admin has disabled
 * with a friendly message instead of a generic "bad credentials" error.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isDisabled()) {
            throw new CustomUserMessageAuthenticationException(
                'This account has been disabled by an administrator. Please contact support.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No extra post-auth checks needed.
    }
}
