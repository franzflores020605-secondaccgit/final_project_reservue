<?php

namespace App\Controller\Api;

use App\Entity\CustomerPackageBooking;
use App\Entity\User;
use App\Enum\CustomerBookingStatus;
use App\Repository\CustomerPackageBookingRepository;
use App\Repository\UserRepository;
use App\Service\CustomerBookingReceiptPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/mobile/v1/my-bookings', name: 'api_mobile_v1_my_bookings_')]
final class MobileMyBookingDetailController extends AbstractController
{
    public function __construct(
        private readonly CustomerPackageBookingRepository $customerPackageBookingRepository,
        private readonly UserRepository $userRepository,
        private readonly CustomerBookingReceiptPdfGenerator $receiptPdfGenerator,
    ) {}

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $booking = $this->resolveBookingForUser($id, $user);
        if (!$booking instanceof CustomerPackageBooking) {
            return $this->json($this->error('Reservation not found.'), 404);
        }

        return $this->json($this->ok($this->serializeBookingDetail($booking)));
    }

    #[Route('/{id}/receipt', name: 'receipt', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function receipt(
        int $id,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $booking = $this->resolveBookingForUser($id, $user);
        if (!$booking instanceof CustomerPackageBooking) {
            return $this->json($this->error('Reservation not found.'), 404);
        }

        if ($booking->getStatus() !== CustomerBookingStatus::Completed) {
            return $this->json($this->error('Receipt is available only for completed bookings.'), 403);
        }

        $pdf = $this->receiptPdfGenerator->generatePdfContent($booking);
        $filename = 'ReserVue-receipt-'.$booking->getReferenceCode().'.pdf';

        return $this->json($this->ok([
            'filename' => $filename,
            'pdfBase64' => base64_encode($pdf),
            'mimeType' => 'application/pdf',
        ]));
    }

    private function resolveBookingForUser(int $id, ?User $user): ?CustomerPackageBooking
    {
        if (!$user instanceof User) {
            return null;
        }

        $actor = $this->resolveApiUser($user);
        if (!$actor instanceof User) {
            return null;
        }

        return $this->customerPackageBookingRepository->findOneByIdForCustomerUser($id, $actor);
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
     * @return array<string, mixed>
     */
    private function serializeBookingDetail(CustomerPackageBooking $b): array
    {
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
            'estimatedTotal' => $b->getEstimatedTotal(),
            'contactFirstName' => $b->getContactFirstName(),
            'contactLastName' => $b->getContactLastName(),
            'contactEmail' => $b->getContactEmail(),
            'contactPhone' => $b->getContactPhone(),
            'contactPassportNumber' => $b->getContactPassportNumber(),
            'specialRequests' => $b->getSpecialRequests(),
            'hasReceipt' => $status === CustomerBookingStatus::Completed,
        ];
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
