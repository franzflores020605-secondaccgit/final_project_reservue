<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\CustomerPackageBooking;
use App\Entity\Traveler;
use App\Entity\TravelPackage;
use App\Entity\User;
use App\Repository\CustomerPackageBookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CustomerBookingSubmissionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerPackageBookingRepository $customerPackageBookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly PackageBookingInventoryService $packageBookingInventoryService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Persists a public booking request and creates a Traveler record from the lead contact.
     *
     * @return array{referenceCode: string}
     */
    public function persistLeadTravelerAndBooking(CustomerPackageBooking $lead, ?User $submittedBy): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $lead->setReferenceCode($this->customerPackageBookingRepository->generateUniqueReferenceCode());
            $lead->setBookingKind($this->resolveBookingKind($lead));

            foreach ($this->validator->validate($lead) as $violation) {
                throw new \InvalidArgumentException($violation->getMessage());
            }

            $traveler = new Traveler();
            $traveler->setFirstName($lead->getContactFirstName() ?? '');
            $traveler->setLastName($lead->getContactLastName() ?? '');
            $traveler->setEmail($lead->getContactEmail());
            $traveler->setPhone($lead->getContactPhone());
            $traveler->setPassportNumber($lead->getContactPassportNumber());
            if ($submittedBy instanceof User) {
                $traveler->setOwner($submittedBy);
            }

            foreach ($this->validator->validate($traveler) as $violation) {
                throw new \InvalidArgumentException($violation->getMessage());
            }

            $this->packageBookingInventoryService->deductForNewBooking($lead);

            $this->entityManager->persist($traveler);
            $lead->setLeadTraveler($traveler);
            if ($submittedBy instanceof User) {
                $lead->setSubmittedBy($submittedBy);
            }

            $this->entityManager->persist($lead);
            $this->entityManager->flush();

            $bookingId = $lead->getId();
            if ($bookingId !== null) {
                $this->auditLogger->log(
                    'Book',
                    'CustomerPackageBooking',
                    $bookingId,
                    $this->formatCustomerBookingAuditDetails($lead)
                );
            }

            $connection->commit();

            return [
                'referenceCode' => (string) $lead->getReferenceCode(),
            ];
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function resolveBookingKind(CustomerPackageBooking $booking): string
    {
        if ($booking->getBookedProduct()) {
            return 'catalog';
        }
        $package = $booking->getTravelPackage();
        if (!$package instanceof TravelPackage) {
            return 'catalog';
        }
        $category = $package->getCategory();
        if (!$category instanceof Category) {
            return 'package';
        }

        $name = mb_strtolower($category->getName() ?? '');

        if (str_contains($name, 'flight')) {
            return 'flight';
        }
        if (str_contains($name, 'tour')) {
            return 'tour';
        }

        return 'package';
    }

    /**
     * Multi-line description for admin audit logs (line breaks + labels; searchable in DataTables).
     */
    private function formatCustomerBookingAuditDetails(CustomerPackageBooking $b): string
    {
        $title = $b->getDisplayTitle();
        $kindLabel = $b->getReservationKindLabel();
        $ref = $b->getReferenceCode() ?? '—';
        $id = $b->getId() ?? 0;

        $offerIdPart = '';
        if ($b->getTravelPackage() !== null) {
            $pid = $b->getTravelPackage()->getId();
            $offerIdPart = $pid !== null ? sprintf(' · package ID %d', $pid) : '';
        } elseif ($b->getBookedProduct() !== null) {
            $pid = $b->getBookedProduct()->getId();
            $offerIdPart = $pid !== null ? sprintf(' · product ID %d', $pid) : '';
        }

        $travel = $b->getTravelDate();
        $travelStr = $travel instanceof \DateTimeInterface ? $travel->format('M j, Y') : '—';
        $n = (int) ($b->getNumberOfTravelers() ?? 0);
        $total = number_format($b->getEstimatedTotal(), 2, '.', ',');
        $status = $b->getStatus()->value;
        $bookingKind = $b->getBookingKind();

        $lines = [
            sprintf('Offer: %s — "%s"%s', $kindLabel, $title, $offerIdPart),
            sprintf('Booking: ID %d · Reference %s · Status %s · Kind %s', $id, $ref, $status, $bookingKind),
            sprintf('Trip: %s · %d traveler(s) · Est. total ₱%s', $travelStr, $n, $total),
        ];

        $priceLine = $b->getPriceDescriptionLine();
        if ($priceLine !== null && $priceLine !== '') {
            $lines[] = 'Pricing: '.$priceLine;
        }

        $detailLine = $b->getCustomerSummaryDetailLine();
        if ($detailLine !== null && $detailLine !== '') {
            $lines[] = 'Details: '.$detailLine;
        }

        $contactName = trim(($b->getContactFirstName() ?? '').' '.($b->getContactLastName() ?? ''));
        $email = (string) ($b->getContactEmail() ?? '');
        $phone = trim((string) ($b->getContactPhone() ?? ''));
        $lines[] = sprintf(
            'Contact: %s · %s · %s',
            $contactName !== '' ? $contactName : '—',
            $email !== '' ? $email : '—',
            $phone !== '' ? $phone : '—'
        );

        $sub = $b->getSubmittedBy();
        if ($sub !== null) {
            $lines[] = sprintf('Account: %s (user ID %s)', $sub->getUserIdentifier(), $sub->getId() ?? '—');
        }

        $special = $b->getSpecialRequests();
        if ($special !== null && trim($special) !== '') {
            $flat = preg_replace('/\s+/u', ' ', trim($special)) ?? trim($special);
            if (function_exists('mb_strlen') && mb_strlen($flat, 'UTF-8') > 400) {
                $flat = mb_substr($flat, 0, 397, 'UTF-8').'…';
            } elseif (strlen($flat) > 400) {
                $flat = substr($flat, 0, 397).'…';
            }
            $lines[] = 'Notes: '.$flat;
        }

        return implode("\n", $lines);
    }
}
