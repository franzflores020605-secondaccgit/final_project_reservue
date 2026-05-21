<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\TravelerRepository;
use App\Repository\CustomerPackageBookingRepository;
use App\Repository\AuditLogRepository;
use App\Repository\TravelPackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\RoleDisplayFormatter;

#[IsGranted(new Expression('is_granted("ROLE_STAFF") or is_granted("ROLE_ADMIN")'))]
class StaffDashboardController extends AbstractController
{
    #[Route('/staff/dashboard', name: 'app_staff_dashboard')]
    public function index(
        ProductRepository $productRepository,
        TravelerRepository $travelerRepository,
        CustomerPackageBookingRepository $customerPackageBookingRepository,
        AuditLogRepository $auditLogRepository,
        TravelPackageRepository $travelPackageRepository,
        RoleDisplayFormatter $roleDisplayFormatter
    ): Response {
        $user = $this->getUser();
        $stats = [
            // Staff workspace is a shared admin/staff inventory (avoid duplicated records).
            'products' => $productRepository->count([]),
            'travelers' => $travelerRepository->count([]),
            'bookings' => $this->isGranted('ROLE_STAFF') ? $customerPackageBookingRepository->count([]) : null,
            'travel_packages' => $this->isGranted('ROLE_STAFF') ? $travelPackageRepository->count([]) : null,
        ];

        // Staff gets a “latest snapshot” of audit logs (same data source as admin → Audit Logs).
        $auditLogs = $auditLogRepository->findRecent(8);

        // Transform audit logs into the same “shape” used by Admin → Audit Logs.
        $recentActivities = \array_map(static function ($log) use ($roleDisplayFormatter) {
            /** @var \App\Entity\AuditLog $log */
            return [
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                'username' => $log->getUsername() ?? 'N/A',
                'roleLabel' => $roleDisplayFormatter->primaryLabel($log->getRoles()),
                'action' => $log->getAction(),
                'affectedData' => $log->getDetails() ?? '—',
            ];
        }, $auditLogs);

        return $this->render('staff_dashboard/index.html.twig', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
        ]);
    }
}

