<?php

namespace App\Controller\Api;

use App\Entity\CustomerPackageBooking;
use App\Entity\Product;
use App\Entity\TravelPackage;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\TravelPackageRepository;
use App\Repository\UserRepository;
use App\Service\CustomerBookingSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/mobile/v1', name: 'api_mobile_v1_')]
final class MobileBookingController extends AbstractController
{
    public function __construct(
        private readonly CustomerBookingSubmissionService $customerBookingSubmissionService,
        private readonly TravelPackageRepository $travelPackageRepository,
        private readonly ProductRepository $productRepository,
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/bookings', name: 'bookings_create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $actor = $this->resolveApiUser($user);
        if (!$actor instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json($this->error('Invalid JSON body.'), 400);
        }

        try {
            $booking = $this->buildBookingFromPayload($data);
            $result = $this->customerBookingSubmissionService->persistLeadTravelerAndBooking(
                $booking,
                $actor,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json($this->error($e->getMessage()), 400);
        } catch (\Throwable $e) {
            return $this->json($this->error('We could not save your booking. Please try again.'), 500);
        }

        return $this->json($this->ok([
            'id' => $booking->getId(),
            'referenceCode' => $result['referenceCode'],
            'message' => sprintf(
                'Thank you! Your request is submitted. Reference: %s. Our team will confirm soon.',
                $result['referenceCode'],
            ),
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildBookingFromPayload(array $data): CustomerPackageBooking
    {
        $booking = new CustomerPackageBooking();

        $context = (string) ($data['bookingContext'] ?? 'package');
        $packageId = isset($data['travelPackageId']) ? (int) $data['travelPackageId'] : 0;
        $productId = isset($data['productId']) ? (int) $data['productId'] : 0;

        if ($context === 'inventory' || $productId > 0) {
            $product = $this->productRepository->find($productId);
            if (!$product instanceof Product || !$product->isShowOnBookPage()) {
                throw new \InvalidArgumentException('That catalog item is not available.');
            }
            $booking->setBookedProduct($product);
            $booking->setTravelPackage(null);
        } else {
            if ($packageId <= 0) {
                throw new \InvalidArgumentException('Choose a travel package to complete this booking.');
            }
            $package = $this->travelPackageRepository->find($packageId);
            if (!$package instanceof TravelPackage || !$package->isPublished()) {
                throw new \InvalidArgumentException('That travel package is not available.');
            }
            $booking->setTravelPackage($package);
            $booking->setBookedProduct(null);
        }

        $travelDate = (string) ($data['travelDate'] ?? '');
        if ($travelDate === '') {
            throw new \InvalidArgumentException('Travel date is required.');
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $travelDate)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d', substr($travelDate, 0, 10));
        if (!$date) {
            throw new \InvalidArgumentException('Invalid travel date.');
        }
        $booking->setTravelDate($date);

        $travelers = (int) ($data['numberOfTravelers'] ?? 1);
        if ($travelers < 1 || $travelers > 20) {
            throw new \InvalidArgumentException('Number of travelers must be between 1 and 20.');
        }
        $booking->setNumberOfTravelers($travelers);

        $booking->setContactFirstName(trim((string) ($data['contactFirstName'] ?? '')));
        $booking->setContactLastName(trim((string) ($data['contactLastName'] ?? '')));
        $booking->setContactEmail(trim((string) ($data['contactEmail'] ?? '')));
        $booking->setContactPhone(trim((string) ($data['contactPhone'] ?? '')) ?: null);
        $booking->setContactPassportNumber(trim((string) ($data['contactPassportNumber'] ?? '')));
        $booking->setSpecialRequests(trim((string) ($data['specialRequests'] ?? '')) ?: null);

        if ($booking->getContactFirstName() === '' || $booking->getContactLastName() === '') {
            throw new \InvalidArgumentException('First and last name are required.');
        }
        if ($booking->getContactEmail() === '') {
            throw new \InvalidArgumentException('Email is required.');
        }
        if ($booking->getContactPassportNumber() === '') {
            throw new \InvalidArgumentException('Passport number is required.');
        }

        return $booking;
    }

    private function resolveApiUser(User $user): ?User
    {
        if ($user->getId() !== null) {
            return $this->userRepository->find($user->getId())
                ?? $this->userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
        }

        return $this->userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{success: true, data: array<string, mixed>, meta: array{}, error: null}
     */
    private function ok(array $data): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => [],
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
