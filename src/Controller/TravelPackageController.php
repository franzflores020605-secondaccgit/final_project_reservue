<?php

namespace App\Controller;

use App\Entity\TravelPackage;
use App\Form\TravelPackageType;
use App\Repository\TravelPackageRepository;
use App\Service\AuditLogger;
use App\Service\DocumentImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Access: ROLE_ADMIN or ROLE_STAFF (see security.yaml access_control for ^/travel-package).
 */
#[Route('/travel-package')]
final class TravelPackageController extends AbstractController
{
    #[Route(name: 'app_travel_package_index', methods: ['GET'])]
    public function index(TravelPackageRepository $travelPackageRepository): Response
    {
        return $this->render('travel_package/index.html.twig', [
            'travel_packages' => $travelPackageRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_travel_package_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        DocumentImageStorage $documentImageStorage,
    ): Response {
        $travelPackage = new TravelPackage();
        $form = $this->createForm(TravelPackageType::class, $travelPackage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->applyUploadedPackageImage($form, $travelPackage, $documentImageStorage)) {
                $this->addFlash('error', 'A package photo is required.');
            } else {
                try {
                    $entityManager->persist($travelPackage);
                    $entityManager->flush();
                    $auditLogger->log('Create', 'TravelPackage', $travelPackage->getId(), sprintf('Created travel package "%s"', $travelPackage->getName()));

                    $this->addFlash('success', 'Travel package created successfully!');

                    return $this->redirectToRoute('app_travel_package_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error creating travel package: '.$e->getMessage());
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please fix the validation errors below.');
        }

        return $this->render('travel_package/new.html.twig', [
            'travel_package' => $travelPackage,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_travel_package_show', methods: ['GET'])]
    public function show(TravelPackage $travelPackage): Response
    {
        return $this->render('travel_package/show.html.twig', [
            'travel_package' => $travelPackage,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_travel_package_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TravelPackage $travelPackage,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        DocumentImageStorage $documentImageStorage,
    ): Response {
        $form = $this->createForm(TravelPackageType::class, $travelPackage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->applyUploadedPackageImage($form, $travelPackage, $documentImageStorage)) {
                $this->addFlash('error', 'This package must have a photo. Upload one or keep the existing image.');
            } else {
                try {
                    $entityManager->flush();
                    $auditLogger->log('Update', 'TravelPackage', $travelPackage->getId(), sprintf('Updated travel package "%s"', $travelPackage->getName()));

                    $this->addFlash('success', 'Travel package updated successfully!');

                    return $this->redirectToRoute('app_travel_package_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error updating travel package: '.$e->getMessage());
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please fix the validation errors below.');
        }

        return $this->render('travel_package/edit.html.twig', [
            'travel_package' => $travelPackage,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_travel_package_delete', methods: ['POST'])]
    public function delete(Request $request, TravelPackage $travelPackage, EntityManagerInterface $entityManager, AuditLogger $auditLogger, DocumentImageStorage $documentImageStorage): Response
    {
        if ($this->isCsrfTokenValid('delete'.$travelPackage->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $id = $travelPackage->getId();
                $name = $travelPackage->getName();
                $documentImageStorage->removeIfManaged($travelPackage->getImagePath());
                $entityManager->remove($travelPackage);
                $entityManager->flush();
                $auditLogger->log('Delete', 'TravelPackage', $id, sprintf('Deleted travel package "%s"', $name));

                $this->addFlash('success', 'Travel package deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting travel package: '.$e->getMessage());
            }
        }

        return $this->redirectToRoute('app_travel_package_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Applies a newly uploaded file to {@see TravelPackage::$imagePath}. Returns false if no usable path remains.
     */
    private function applyUploadedPackageImage(FormInterface $form, TravelPackage $travelPackage, DocumentImageStorage $storage): bool
    {
        $file = $form->get('imageFile')->getData();
        if ($file instanceof UploadedFile) {
            $storage->removeIfManaged($travelPackage->getImagePath());
            $travelPackage->setImagePath($storage->store($file));
        }

        $path = trim((string) $travelPackage->getImagePath());
        if ($path === '') {
            $form->get('imageFile')->addError(new FormError('Please upload a package image from your computer.'));

            return false;
        }

        return true;
    }
}
