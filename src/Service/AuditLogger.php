<?php

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function log(string $action, string $entity, ?int $entityId = null, ?string $details = null): void
    {
        $user = $this->security->getUser();
        $log = new AuditLog();
        $log->setAction($action)
            ->setEntity($entity)
            ->setEntityId($entityId)
            ->setDetails($details)
            ->setUsername($user?->getUserIdentifier())
            ->setUserId(method_exists($user, 'getId') ? $user?->getId() : null)
            ->setRoles($this->determinePrimaryRole($user?->getRoles() ?? []));

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();
        if ($ip) {
            $log->setIp($ip);
        }

        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * Return an array with a single primary role (highest priority) or empty array.
     */
    private function determinePrimaryRole(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        $priority = [
            'ROLE_ADMIN',
            'ROLE_STAFF',
            'ROLE_USER',
        ];

        foreach ($priority as $p) {
            if (in_array($p, $roles, true)) {
                return [$p];
            }
        }

        // Fallback: return the first role if none of the priorities matched
        return [reset($roles)];
    }
}

