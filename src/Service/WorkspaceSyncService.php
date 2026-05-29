<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\CustomerPackageBooking;
use App\Entity\Product;
use App\Entity\Traveler;
use App\Entity\TravelPackage;
use App\Entity\User;
use App\Enum\CustomerBookingStatus;
use App\Repository\AuditLogRepository;
use App\Repository\CategoryRepository;
use App\Repository\CustomerPackageBookingRepository;
use App\Repository\ProductRepository;
use App\Repository\TravelerRepository;
use App\Repository\TravelPackageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lightweight fingerprints for admin workspace live-update checks (no full page poll).
 */
final class WorkspaceSyncService
{
    private const ADMIN_SCOPES = ['dashboard', 'audit_logs', 'admin_user'];
    private const SHARED_SCOPES = [
        'bookings',
        'product',
        'category',
        'traveler',
        'travel_package',
        'staff_dashboard',
    ];

    public function __construct(
        private readonly CustomerPackageBookingRepository $customerPackageBookingRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TravelerRepository $travelerRepository,
        private readonly TravelPackageRepository $travelPackageRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function isScopeAllowed(string $scope, bool $isAdmin, bool $isStaff): bool
    {
        if (\in_array($scope, self::ADMIN_SCOPES, true)) {
            return $isAdmin;
        }
        if (\in_array($scope, self::SHARED_SCOPES, true)) {
            return $isAdmin || $isStaff;
        }

        return false;
    }

    /**
     * @return array{fingerprint: string, total?: int, latestId?: int}
     */
    public function snapshot(string $scope): array
    {
        return match ($scope) {
            'dashboard' => $this->dashboardSnapshot(),
            'audit_logs' => $this->auditLogRepository->getSyncSnapshot(),
            'bookings' => $this->customerPackageBookingRepository->getAdminListSyncSnapshot(),
            'product' => $this->entitySnapshot(Product::class),
            'category' => $this->entitySnapshot(Category::class),
            'traveler' => $this->entitySnapshot(Traveler::class),
            'travel_package' => $this->entitySnapshot(TravelPackage::class),
            'admin_user' => $this->entitySnapshot(User::class),
            'staff_dashboard' => $this->staffDashboardSnapshot(),
            default => throw new \InvalidArgumentException('Unknown sync scope: '.$scope),
        };
    }

    /**
     * @return array{fingerprint: string}
     */
    private function dashboardSnapshot(): array
    {
        $bookings = $this->customerPackageBookingRepository->getAdminListSyncSnapshot();
        $audit = $this->auditLogRepository->getSyncSnapshot();
        $pending = $this->customerPackageBookingRepository->count(['status' => CustomerBookingStatus::Pending]);
        $products = $this->productRepository->count([]);

        return [
            'fingerprint' => implode(':', [
                $bookings['total'],
                $bookings['latestId'],
                $audit['total'],
                $audit['latestId'],
                $pending,
                $products,
            ]),
        ];
    }

    /**
     * @return array{fingerprint: string}
     */
    private function staffDashboardSnapshot(): array
    {
        $bookings = $this->customerPackageBookingRepository->getAdminListSyncSnapshot();
        $products = $this->productRepository->count([]);
        $travelers = $this->travelerRepository->count([]);
        $packages = $this->travelPackageRepository->count([]);

        return [
            'fingerprint' => implode(':', [
                $bookings['total'],
                $bookings['latestId'],
                $products,
                $travelers,
                $packages,
            ]),
        ];
    }

    /**
     * @param class-string $entityClass
     *
     * @return array{fingerprint: string, total: int, latestId: int}
     */
    private function entitySnapshot(string $entityClass): array
    {
        $total = (int) $this->entityManager->getRepository($entityClass)->count([]);
        $latestId = (int) ($this->entityManager->createQueryBuilder()
            ->select('MAX(e.id)')
            ->from($entityClass, 'e')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        return [
            'total' => $total,
            'latestId' => $latestId,
            'fingerprint' => $total.':'.$latestId,
        ];
    }
}
