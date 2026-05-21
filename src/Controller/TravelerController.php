<?php

namespace App\Controller;

use App\Entity\Traveler;
use App\Entity\User;
use App\Form\TravelerType;
use App\Repository\TravelerRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/traveler')]
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF") or is_granted("ROLE_USER")'))]
final class TravelerController extends AbstractController
{
    #[Route(name: 'app_traveler_index', methods: ['GET'])]
    public function index(TravelerRepository $travelerRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $travelers = $travelerRepository->findForDashboardUser(
            $user,
            $this->isGranted('ROLE_ADMIN'),
            $this->isGranted('ROLE_STAFF'),
        );

        return $this->render('traveler/index.html.twig', [
            'travelers' => $travelers,
        ]);
    }

    #[Route('/new', name: 'app_traveler_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $traveler = new Traveler();
        $form = $this->createForm(TravelerType::class, $traveler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $traveler->setOwner($this->getUser());
            $entityManager->persist($traveler);
            $entityManager->flush();
            $auditLogger->log('Create', 'Traveler', $traveler->getId(), sprintf('Created traveler "%s %s"', $traveler->getFirstName(), $traveler->getLastName()));

            return $this->redirectToRoute('app_traveler_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('traveler/new.html.twig', [
            'traveler' => $traveler,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_traveler_show', methods: ['GET'])]
    public function show(Traveler $traveler): Response
    {
        $this->assertOwnershipOrAdmin($traveler);
        return $this->render('traveler/show.html.twig', [
            'traveler' => $traveler,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_traveler_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Traveler $traveler, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->assertOwnershipOrAdmin($traveler);
        $form = $this->createForm(TravelerType::class, $traveler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $auditLogger->log('Update', 'Traveler', $traveler->getId(), sprintf('Updated traveler "%s %s"', $traveler->getFirstName(), $traveler->getLastName()));

            return $this->redirectToRoute('app_traveler_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('traveler/edit.html.twig', [
            'traveler' => $traveler,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_traveler_delete', methods: ['POST'])]
    public function delete(Request $request, Traveler $traveler, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->assertOwnershipOrAdmin($traveler);
        if ($this->isCsrfTokenValid('delete'.$traveler->getId(), $request->getPayload()->getString('_token'))) {
            $name = trim($traveler->getFirstName().' '.$traveler->getLastName());
            $entityManager->remove($traveler);
            $entityManager->flush();
            $auditLogger->log('Delete', 'Traveler', $traveler->getId(), sprintf('Deleted traveler "%s"', $name));
        }

        return $this->redirectToRoute('app_traveler_index', [], Response::HTTP_SEE_OTHER);
    }

    private function assertOwnershipOrAdmin(Traveler $traveler): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($this->isGranted('ROLE_STAFF')) {
            return;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($traveler->getOwner() === $user) {
            return;
        }

        throw $this->createAccessDeniedException('You can only access your own travelers.');
    }
}
