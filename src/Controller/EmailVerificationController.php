<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(
        Request $request,
        EmailVerificationService $emailVerificationService,
    ): Response {
        $token = $request->query->get('token');

        if (!$token || !\is_string($token)) {
            return $this->render('email_verification/result.html.twig', [
                'success' => false,
                'title' => 'Verification failed',
                'message' => 'Verification link is missing or invalid.',
            ], new Response('', 400));
        }

        $user = $emailVerificationService->verifyToken($token);

        if (!$user) {
            return $this->render('email_verification/result.html.twig', [
                'success' => false,
                'title' => 'Verification failed',
                'message' => 'This link is invalid or was already used.',
            ], new Response('', 400));
        }

        return $this->render('email_verification/result.html.twig', [
            'success' => true,
            'title' => 'Email verified',
            'message' => sprintf('Thank you, %s! Your email is confirmed.', $user->getUsername()),
        ]);
    }
}
