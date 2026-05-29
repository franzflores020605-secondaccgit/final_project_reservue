<?php

namespace App\Controller;

use App\Entity\CustomerPackageBooking;
use App\Enum\CustomerBookingStatus;
use App\Form\CustomerPackageBookingStatusType;
use App\Repository\CustomerPackageBookingRepository;
use App\Service\AuditLogger;
use App\Service\PackageBookingInventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lists customer-submitted package (and flight-category) booking requests for staff review.
 */
#[Route('/bookings')]
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
final class BookingsController extends AbstractController
{
    #[Route(name: 'app_bookings_index', methods: ['GET'])]
    public function index(CustomerPackageBookingRepository $customerPackageBookingRepository): Response
    {
        $bookings = $customerPackageBookingRepository->findBy([], ['id' => 'ASC']);

        return $this->render('bookings/index.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/{id}', name: 'app_bookings_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(CustomerPackageBooking $customerPackageBooking): Response
    {
        return $this->render('bookings/show.html.twig', [
            'booking' => $customerPackageBooking,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_bookings_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        CustomerPackageBooking $customerPackageBooking,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        PackageBookingInventoryService $packageBookingInventoryService,
    ): Response {
        if ($customerPackageBooking->isStatusLocked()) {
            $this->addFlash('warning', 'This booking is '.$customerPackageBooking->getStatus()->value.' and can no longer be edited.');

            return $this->redirectToRoute('app_bookings_show', ['id' => $customerPackageBooking->getId()], Response::HTTP_SEE_OTHER);
        }

        $previousStatus = $customerPackageBooking->getStatus();
        $form = $this->createForm(CustomerPackageBookingStatusType::class, $customerPackageBooking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $customerPackageBooking->getStatus();
            if ($previousStatus !== CustomerBookingStatus::Cancelled && $newStatus === CustomerBookingStatus::Cancelled) {
                $packageBookingInventoryService->restoreAfterCancellation($customerPackageBooking);
            }
            $entityManager->flush();
            $auditLogger->log(
                'Update',
                'CustomerPackageBooking',
                $customerPackageBooking->getId(),
                sprintf(
                    "Booking: \"%s\"\nReference: %s · ID: %d\nNew status: %s",
                    $customerPackageBooking->getDisplayTitle(),
                    $customerPackageBooking->getReferenceCode() ?? '—',
                    $customerPackageBooking->getId() ?? 0,
                    $customerPackageBooking->getStatus()->value
                )
            );
            $this->addFlash('success', 'Booking request updated.');

            return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('bookings/edit.html.twig', [
            'booking' => $customerPackageBooking,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/status', name: 'app_bookings_quick_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function quickStatus(
        Request $request,
        CustomerPackageBooking $customerPackageBooking,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        PackageBookingInventoryService $packageBookingInventoryService,
    ): Response {
        $id = $customerPackageBooking->getId();
        if (!$this->isCsrfTokenValid('status'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($customerPackageBooking->isStatusLocked()) {
            $this->addFlash('warning', 'This booking is finalised and its status cannot be changed.');

            return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
        }

        $raw = $request->request->get('status');
        $next = \is_string($raw) ? CustomerBookingStatus::tryFrom($raw) : null;
        if (!$next instanceof CustomerBookingStatus) {
            $this->addFlash('error', 'Invalid booking status.');

            return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
        }

        $previous = $customerPackageBooking->getStatus();
        $customerPackageBooking->setStatus($next);
        if ($previous !== CustomerBookingStatus::Cancelled && $next === CustomerBookingStatus::Cancelled) {
            $packageBookingInventoryService->restoreAfterCancellation($customerPackageBooking);
        }
        $entityManager->flush();
        $auditLogger->log(
            'Update',
            'CustomerPackageBooking',
            $customerPackageBooking->getId(),
            sprintf(
                "Booking: \"%s\"\nReference: %s · ID: %d\nStatus: %s → %s",
                $customerPackageBooking->getDisplayTitle(),
                $customerPackageBooking->getReferenceCode() ?? '—',
                $customerPackageBooking->getId() ?? 0,
                $previous->value,
                $next->value
            )
        );
        $this->addFlash('success', 'Status updated to '.$next->name.'.');

        return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_bookings_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        CustomerPackageBooking $customerPackageBooking,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        PackageBookingInventoryService $packageBookingInventoryService,
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$customerPackageBooking->getId(), $request->getPayload()->getString('_token'))) {
            $id = $customerPackageBooking->getId();
            $ref = $customerPackageBooking->getReferenceCode() ?? '—';
            $title = $customerPackageBooking->getDisplayTitle();
            $packageBookingInventoryService->restoreOnAdminDeletionIfNeeded($customerPackageBooking);
            $entityManager->remove($customerPackageBooking);
            $entityManager->flush();
            $this->addFlash('success', 'Booking request removed.');
            if ($id !== null) {
                $auditLogger->log(
                    'Delete',
                    'CustomerPackageBooking',
                    $id,
                    sprintf("Deleted booking \"%s\"\nReference: %s · ID: %d", $title, $ref, $id)
                );
            }
        }

        return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
    }
}
