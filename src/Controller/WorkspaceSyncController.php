<?php

namespace App\Controller;

use App\Service\WorkspaceSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/workspace')]
#[IsGranted('ROLE_USER')]
final class WorkspaceSyncController extends AbstractController
{
    #[Route('/sync-check/{scope}', name: 'app_workspace_sync_check', methods: ['GET'])]
    public function syncCheck(string $scope, WorkspaceSyncService $workspaceSyncService): JsonResponse
    {
        if (!$workspaceSyncService->isScopeAllowed(
            $scope,
            $this->isGranted('ROLE_ADMIN'),
            $this->isGranted('ROLE_STAFF'),
        )) {
            throw $this->createAccessDeniedException();
        }

        return $this->json($workspaceSyncService->snapshot($scope));
    }
}
