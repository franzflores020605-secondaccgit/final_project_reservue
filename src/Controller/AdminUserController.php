<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminUserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    #[Route('/', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $showArchived = $request->query->getBoolean('show_archived');

        return $this->render('admin_user/index.html.twig', [
            'users' => $userRepository->findForAdminListing($showArchived),
            'show_archived' => $showArchived,
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogger $auditLogger
    ): Response {
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user, ['require_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            if ($user->getStatus() === '') {
                $user->setStatus('active');
            }

            $entityManager->persist($user);
            $entityManager->flush();

            // Log admin-created user
            $auditLogger->log('Create', 'User', $user->getId(), sprintf('Admin created user "%s" (ID: %d)', $user->getUserIdentifier(), $user->getId()));

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogger $auditLogger
    ): Response {
        $form = $this->createForm(AdminUserType::class, $user, ['require_password' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if (!empty($plainPassword)) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();

            // Log admin update
            $auditLogger->log('Update', 'User', $user->getId(), sprintf('Admin updated user "%s" (ID: %d)', $user->getUserIdentifier(), $user->getId()));

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            // capture info before deletion
            $username = $user->getUserIdentifier();
            $userId = $user->getId();

            $entityManager->remove($user);
            $entityManager->flush();

            // Log admin deletion
            $auditLogger->log('Delete', 'User', $userId, sprintf('Admin deleted user "%s" (ID: %d)', $username, $userId));

            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
 
