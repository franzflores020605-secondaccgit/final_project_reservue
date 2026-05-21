<?php

namespace App\Service;

/**
 * Maps Symfony role constants to a single admin-friendly label (highest privilege wins).
 */
final class RoleDisplayFormatter
{
    public function primaryLabel(array $roles): string
    {
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'ADMIN';
        }
        if (in_array('ROLE_STAFF', $roles, true)) {
            return 'STAFF';
        }
        if (in_array('ROLE_USER', $roles, true)) {
            return 'USER';
        }

        $first = reset($roles);
        if (\is_string($first) && str_starts_with($first, 'ROLE_')) {
            return strtoupper(substr($first, 5));
        }

        return $first ? (string) $first : '—';
    }
}
