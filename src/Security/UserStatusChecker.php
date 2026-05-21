<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Enforces account status after the user is loaded (login and every authenticated request).
 */
final class UserStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        match ($user->getStatus()) {
            'disabled' => throw new CustomUserMessageAccountStatusException('This account is disabled. Sign-in is not allowed, even with the correct password.'),
            'archived' => throw new CustomUserMessageAccountStatusException('This account has been archived. It is no longer available for sign-in.'),
            default => null,
        };
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Require email verification before allowing login/session usage.
        // Google OAuth logins are marked verified in GoogleAuthenticator, so they are unaffected.
        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email first before logging in.');
        }
    }
}
