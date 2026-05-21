<?php

namespace App\Controller;

use App\Form\AdminProfileType;
use App\Form\AdminChangePasswordType;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class StaffAccountController extends AbstractController
{
    #[Route('/account', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em, AuditLogger $auditLogger): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(AdminProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            // Log staff profile update
            $auditLogger->log('Update', 'User', $user->getId(), sprintf('Staff updated their profile "%s" (ID: %d)', $user->getUserIdentifier(), $user->getId()));

            $this->addFlash('success', 'Profile updated.');

            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('admin_account/profile.html.twig', [
            'form' => $form,
            'user' => $user,
            'profile_route' => 'app_account_profile',
            'password_route' => 'app_account_password',
            'logs_route' => null,
            'users_route' => null,
        ]);
    }

    #[Route('/account/password', name: 'app_account_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogger $auditLogger
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(AdminChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = $form->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $current)) {
                $form->get('currentPassword')->addError(new \Symfony\Component\Form\FormError('Current password is incorrect.'));
            } else {
                $newPassword = $form->get('newPassword')->getData();
                $hashed = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashed);
                $em->flush();

                // Log staff password change
                $auditLogger->log('Update', 'User', $user->getId(), sprintf('Staff changed password for "%s" (ID: %d)', $user->getUserIdentifier(), $user->getId()));

                $this->addFlash('success', 'Password updated.');

                return $this->redirectToRoute('app_account_profile');
            }
        }

        return $this->render('admin_account/password.html.twig', [
            'form' => $form,
            'profile_route' => 'app_account_profile',
            'password_route' => 'app_account_password',
            'logs_route' => null,
            'users_route' => null,
        ]);
    }
}

