<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService,
        AuthenticationUtils $authenticationUtils,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                /** @var string $plainPassword */
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
                // Ensure newly registered accounts are identified as customers.
                $user->setRoles(['ROLE_USER']);

                $verificationToken = $emailVerificationService->generateVerificationToken();
                $user->setVerificationToken($verificationToken);
                $user->setIsVerified(false);

                $entityManager->persist($user);
                $entityManager->flush();

                $verificationUrl = $this->generateUrl(
                    'app_verify_email',
                    ['token' => $verificationToken],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $emailVerificationService->sendVerificationEmail($user, $verificationUrl);

                $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');

                return $this->redirectToRoute('app_landing', ['signin' => '1']);
            }

            return $this->render('landing_page/index.html.twig', [
                'landing_inner' => false,
                'landing_enable_scroll_spy' => true,
                'last_username' => $authenticationUtils->getLastUsername(),
                'login_error' => $authenticationUtils->getLastAuthenticationError(),
                'open_login_modal' => false,
                'open_register_modal' => true,
                'registrationForm' => $form->createView(),
            ]);
        }

        return $this->redirectToRoute('app_landing', ['register' => '1']);
    }
}
