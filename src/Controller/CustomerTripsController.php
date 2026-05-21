<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\CustomerBookingStatus;
use App\Repository\CustomerPackageBookingRepository;
use App\Service\CustomerBookingReceiptPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customer')]
#[IsGranted(new Expression('is_granted("ROLE_USER") and !is_granted("ROLE_STAFF") and !is_granted("ROLE_ADMIN")'))]
final class CustomerTripsController extends AbstractController
{
    #[Route('/my-trips', name: 'app_customer_my_trips', methods: ['GET'])]
    public function index(CustomerPackageBookingRepository $customerPackageBookingRepository): Response
    {
        $user = $this->requireUser();

        return $this->render('customer_trips/index.html.twig', [
            'bookings' => $customerPackageBookingRepository->findForCustomerUser($user),
        ]);
    }

    #[Route('/my-trips/{id}', name: 'app_customer_my_trip_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, CustomerPackageBookingRepository $customerPackageBookingRepository): Response
    {
        $user = $this->requireUser();
        $booking = $customerPackageBookingRepository->findOneByIdForCustomerUser($id, $user);
        if (!$booking) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        return $this->render('customer_trips/show.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/my-trips/{id}/receipt', name: 'app_customer_my_trip_receipt', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function receipt(
        int $id,
        CustomerPackageBookingRepository $customerPackageBookingRepository,
        CustomerBookingReceiptPdfGenerator $customerBookingReceiptPdfGenerator,
    ): Response {
        $user = $this->requireUser();
        $booking = $customerPackageBookingRepository->findOneByIdForCustomerUser($id, $user);
        if (!$booking) {
            throw $this->createNotFoundException('Reservation not found.');
        }
        if ($booking->getStatus() !== CustomerBookingStatus::Completed) {
            throw $this->createAccessDeniedException('A receipt is available only for completed trips.');
        }

        $pdf = $customerBookingReceiptPdfGenerator->generatePdfContent($booking);
        $filename = 'ReserVue-receipt-'.$booking->getReferenceCode().'.pdf';

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
