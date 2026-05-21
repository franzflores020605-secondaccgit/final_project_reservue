<?php

namespace App\Service;

use App\Entity\CustomerPackageBooking;
use App\Entity\Product;
use App\Entity\TravelPackage;
use App\Enum\CustomerBookingStatus;
use App\Repository\TravelPackageRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deducts linked product quantities when a customer books a travel package (per traveler),
 * and restores them when a booking is cancelled or removed (except completed trips).
 */
final class PackageBookingInventoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TravelPackageRepository $travelPackageRepository,
    ) {
    }

    /**
     * Subtracts {@see CustomerPackageBooking::getNumberOfTravelers()} units from each product
     * included in the booked package. Sets inventory tracking on the booking.
     *
     * @throws \InvalidArgumentException when stock is insufficient
     */
    public function deductForNewBooking(CustomerPackageBooking $booking): void
    {
        $travelers = (int) ($booking->getNumberOfTravelers() ?? 0);
        if ($travelers < 1) {
            throw new \InvalidArgumentException('Number of travelers must be at least 1.');
        }

        $booked = $booking->getBookedProduct();
        if ($booked instanceof Product && $booked->getId()) {
            $products = $this->loadProductsForUpdate([(int) $booked->getId()]);
            $product = $products[0] ?? null;
            if (!$product instanceof Product) {
                throw new \InvalidArgumentException('Catalog product not found.');
            }
            $available = (int) $product->getQuantity();
            if ($available < $travelers) {
                throw new \InvalidArgumentException(sprintf(
                    'Insufficient stock for "%s" (%d available; need %d for this booking).',
                    (string) $product->getName(),
                    $available,
                    $travelers
                ));
            }
            $product->setQuantity($available - $travelers);
            $booking->setInventoryDeducted(true);

            return;
        }

        $package = $booking->getTravelPackage();
        if (!$package instanceof TravelPackage || !$package->getId()) {
            $booking->setInventoryDeducted(false);

            return;
        }

        $managed = $this->travelPackageRepository->findWithProductsById((int) $package->getId());
        if (!$managed instanceof TravelPackage) {
            throw new \InvalidArgumentException('Travel package not found.');
        }

        $ids = [];
        foreach ($managed->getProducts() as $p) {
            $id = $p->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        if ($ids === []) {
            $booking->setInventoryDeducted(false);

            return;
        }

        $products = $this->loadProductsForUpdate($ids);

        foreach ($products as $product) {
            $available = (int) $product->getQuantity();
            if ($available < $travelers) {
                throw new \InvalidArgumentException(sprintf(
                    'Insufficient stock for "%s" (%d available; need %d unit(s) per included product for %d traveler(s)).',
                    (string) $product->getName(),
                    $available,
                    $travelers,
                    $travelers
                ));
            }
        }

        foreach ($products as $product) {
            $product->setQuantity((int) $product->getQuantity() - $travelers);
        }

        $booking->setInventoryDeducted(true);
    }

    /**
     * Returns previously deducted units to each linked product (e.g. staff cancelled the request).
     *
     * Runs inside a DB transaction because {@see loadProductsForUpdate} uses pessimistic locks.
     */
    public function restoreAfterCancellation(CustomerPackageBooking $booking): void
    {
        if (!$booking->isInventoryDeducted()) {
            return;
        }

        $this->entityManager->getConnection()->transactional(function () use ($booking): void {
            $this->applyPositiveDelta($booking);
            $booking->setInventoryDeducted(false);
        });
    }

    /**
     * When an admin deletes a booking row: put stock back unless the trip was already completed
     * (inventory treated as consumed).
     */
    public function restoreOnAdminDeletionIfNeeded(CustomerPackageBooking $booking): void
    {
        if (CustomerBookingStatus::Completed === $booking->getStatus()) {
            return;
        }

        $this->restoreAfterCancellation($booking);
    }

    private function applyPositiveDelta(CustomerPackageBooking $booking): void
    {
        $travelers = (int) ($booking->getNumberOfTravelers() ?? 0);
        if ($travelers < 1) {
            return;
        }

        $booked = $booking->getBookedProduct();
        if ($booked instanceof Product && $booked->getId()) {
            $products = $this->loadProductsForUpdate([(int) $booked->getId()]);
            foreach ($products as $product) {
                $product->setQuantity((int) $product->getQuantity() + $travelers);
            }

            return;
        }

        $package = $booking->getTravelPackage();
        if (!$package instanceof TravelPackage || !$package->getId()) {
            return;
        }

        $managed = $this->travelPackageRepository->findWithProductsById((int) $package->getId());
        if (!$managed instanceof TravelPackage) {
            return;
        }

        $ids = [];
        foreach ($managed->getProducts() as $p) {
            $id = $p->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        if ($ids === []) {
            return;
        }

        $products = $this->loadProductsForUpdate($ids);
        foreach ($products as $product) {
            $product->setQuantity((int) $product->getQuantity() + $travelers);
        }
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Product>
     */
    private function loadProductsForUpdate(array $ids): array
    {
        /** @var list<Product> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();

        return $rows;
    }
}
