<?php

namespace App\Controller\Api;

use App\Entity\CustomerPackageBooking;
use App\Enum\CustomerBookingStatus;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CustomerPackageBookingRepository;
use App\Repository\ProductRepository;
use App\Repository\TravelerRepository;
use App\Repository\TravelPackageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/mobile/v1', name: 'api_mobile_v1_')]
class MobileV1Controller extends AbstractController
{
    #[Route('/travelers', name: 'travelers_index', methods: ['GET'])]
    public function travelersIndex(
        #[CurrentUser] ?User $user,
        TravelerRepository $travelers,
        UserRepository $userRepository,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $actor = $this->resolveApiUser($user, $userRepository);
        if (!$actor instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $items = $travelers->findForDashboardUser(
            $actor,
            $this->isGranted('ROLE_ADMIN'),
            $this->isGranted('ROLE_STAFF')
        );

        $data = array_map(static fn ($t) => [
            'id' => $t->getId(),
            'firstName' => $t->getFirstName(),
            'lastName' => $t->getLastName(),
            'passportNumber' => $t->getPassportNumber(),
            'email' => $t->getEmail(),
            'phone' => $t->getPhone(),
        ], $items);

        return $this->json($this->ok($data, ['count' => \count($data)]));
    }

    #[Route('/traveler-packages', name: 'traveler_packages_index', methods: ['GET'])]
    public function travelerPackagesIndex(TravelPackageRepository $packages): JsonResponse
    {
        $items = $packages->findPublishedForCatalog();

        $data = array_map(static fn ($p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'shortDescription' => $p->getShortDescription(),
            'durationLabel' => $p->getDurationLabel(),
            'pricePerPerson' => $p->getPricePerPersonFloat(),
            'imagePath' => $p->getImagePath(),
            'cardImagePath' => $p->getCardImagePath(),
            'isPublished' => $p->isPublished(),
            'createdAt' => $p->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'category' => $p->getCategory() ? [
                'id' => $p->getCategory()->getId(),
                'name' => method_exists($p->getCategory(), 'getName') ? $p->getCategory()->getName() : null,
            ] : null,
        ], $items);

        return $this->json($this->ok($data, ['count' => \count($data)]));
    }

    /**
     * Authenticated catalog products (same rules as the web book page: {@see ProductRepository::findEligibleForBookPage}).
     * Uses JWT from /api/login — aligns with {@see Product} API resource + owner/category data.
     */
    /** Public read-only — same book-page products as the website (no DB schema changes). */
    #[Route('/products', name: 'products_catalog', methods: ['GET'])]
    public function productsCatalog(ProductRepository $productRepository): JsonResponse
    {
        $items = $productRepository->findEligibleForBookPage();

        $data = array_map(static function (Product $p) {
            return [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'description' => $p->getDescription(),
                'quantity' => $p->getQuantity(),
                'price' => $p->getPrice(),
                'imagePath' => $p->getImagePath(),
                'showOnBookPage' => $p->isShowOnBookPage(),
                'category' => $p->getCategory() ? [
                    'id' => $p->getCategory()->getId(),
                    'name' => $p->getCategory()->getName(),
                ] : null,
                'ownerId' => $p->getOwner()?->getId(),
            ];
        }, $items);

        return $this->json($this->ok($data, ['count' => \count($data)]));
    }

    /**
     * Customer reservations (same visibility rules as web /customer/my-trips).
     */
    #[Route('/my-bookings', name: 'my_bookings', methods: ['GET'])]
    public function myBookings(
        #[CurrentUser] ?User $user,
        CustomerPackageBookingRepository $customerPackageBookingRepository,
        UserRepository $userRepository,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $actor = $this->resolveApiUser($user, $userRepository);
        if (!$actor instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $items = $customerPackageBookingRepository->findForCustomerUser($actor);

        $data = array_map(static function (CustomerPackageBooking $b) {
            $status = $b->getStatus();

            return [
                'id' => $b->getId(),
                'referenceCode' => $b->getReferenceCode(),
                'displayTitle' => $b->getDisplayTitle(),
                'summaryDetail' => $b->getCustomerSummaryDetailLine(),
                'kindLabel' => $b->getReservationKindLabel(),
                'status' => $status->value,
                'statusLabel' => match ($status) {
                    CustomerBookingStatus::Pending => 'Processing',
                    CustomerBookingStatus::Completed => 'Completed',
                    CustomerBookingStatus::Cancelled => 'Cancelled',
                },
                'travelDate' => $b->getTravelDate()?->format('Y-m-d'),
                'numberOfTravelers' => $b->getNumberOfTravelers(),
                'imagePath' => $b->getSummaryImagePath(),
                'createdAt' => $b->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'priceDescriptionLine' => $b->getPriceDescriptionLine(),
                'hasReceipt' => $status === CustomerBookingStatus::Completed,
            ];
        }, $items);

        return $this->json($this->ok($data, ['count' => \count($data)]));
    }

    /**
     * JWT / API firewall may pass a non-persisted or detached User; reload from DB so FK matches in queries work.
     */
    private function resolveApiUser(User $user, UserRepository $userRepository): ?User
    {
        if ($user->getId() !== null) {
            return $userRepository->find($user->getId())
                ?? $userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
        }

        return $userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
    }

    /**
     * Standard envelope for mobile consumers.
     *
     * @param array<mixed> $data
     * @param array<string, mixed> $meta
     *
     * @return array{success: true, data: array<mixed>, meta: array<string, mixed>, error: null}
     */
    private function ok(array $data, array $meta = []): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $meta,
            'error' => null,
        ];
    }

    /**
     * @return array{success: false, data: null, meta: array{}, error: array{message: string}}
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'data' => null,
            'meta' => [],
            'error' => ['message' => $message],
        ];
    }
}
