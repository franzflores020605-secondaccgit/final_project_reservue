<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\TravelerRepository;
use App\Repository\CustomerPackageBookingRepository;
use App\Repository\TravelPackageRepository;
use App\Repository\UserRepository;
use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use App\Enum\CustomerBookingStatus;
use App\Service\RoleDisplayFormatter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        TravelerRepository $travelerRepository,
        CustomerPackageBookingRepository $customerPackageBookingRepository,
        TravelPackageRepository $travelPackageRepository,
        UserRepository $userRepository,
        AuditLogRepository $auditLogRepository,
        RoleDisplayFormatter $roleDisplayFormatter
    ): Response {

        $products = $productRepository->findAll();
        $allUsers = $userRepository->findAll();
        $staffCount = 0;
        foreach ($allUsers as $user) {
            if (in_array('ROLE_STAFF', $user->getRoles(), true)) {
                $staffCount++;
            }
        }

        $stats = [
            'products' => $productRepository->count([]),
            'categories' => $categoryRepository->count([]),
            'travelers' => $travelerRepository->count([]),
            'bookings' => $customerPackageBookingRepository->count([]),
            'users' => count($allUsers),
            'staff' => $staffCount,
            'records' => $productRepository->count([]) + $categoryRepository->count([]) + $travelerRepository->count([]) + $customerPackageBookingRepository->count([]),
        ];

        $bookingStats = [
            'total' => $customerPackageBookingRepository->count([]),
            'pending' => $customerPackageBookingRepository->count(['status' => CustomerBookingStatus::Pending]),
            'completed' => $customerPackageBookingRepository->count(['status' => CustomerBookingStatus::Completed]),
            'cancelled' => $customerPackageBookingRepository->count(['status' => CustomerBookingStatus::Cancelled]),
        ];

        $productsInventoryValue = $productRepository->sumStockLineValue();
        $packagesCatalogValue = $travelPackageRepository->sumListedPricePerPerson();
        $inventoryStats = [
            'products_value' => $productsInventoryValue,
            'packages_value' => $packagesCatalogValue,
            'total_value' => $productsInventoryValue + $packagesCatalogValue,
            'sold_traveler_seats' => $customerPackageBookingRepository->sumTravelersForStatus(CustomerBookingStatus::Completed),
        ];

        $recentLogs = $auditLogRepository->findRecent(8);
        $recentActivities = array_map(
            static function (AuditLog $log) use ($roleDisplayFormatter): array {
                return [
                    'at' => $log->getCreatedAt(),
                    'username' => $log->getUsername() ?? 'N/A',
                    'roleLabel' => $roleDisplayFormatter->primaryLabel($log->getRoles()),
                    'action' => $log->getAction(),
                    'affectedData' => $log->getDetails() ?? '—',
                ];
            },
            $recentLogs
        );

        return $this->render('dashboard/index.html.twig', [
            'products' => $products,
            'stats' => $stats,
            'bookingStats' => $bookingStats,
            'inventoryStats' => $inventoryStats,
            'recentActivities' => $recentActivities,
        ]);
    }
}
