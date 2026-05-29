<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mobile/v1/auth', name: 'api_mobile_v1_auth_')]
final class MobileAuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON body.'], 400);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->json([
                'success' => false,
                'message' => 'Username and password are required.',
            ], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'No account with that username. Create an account in the app first.',
            ], 401);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Incorrect password.',
            ], 401);
        }

        $roles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
            return $this->json([
                'success' => false,
                'message' => 'Staff and admin accounts must sign in on the ReserVue website.',
            ], 403);
        }

        if (!$user->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in.',
                'verified' => false,
            ], 403);
        }

        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Server login is not configured (JWT keys). Set JWT_PASSPHRASE on Railway and redeploy.',
                'detail' => $this->getParameter('kernel.debug') ? $e->getMessage() : null,
            ], 500);
        }

        $this->auditLogger->logForUser(
            $user,
            'Login',
            'Auth',
            $user->getId(),
            'User logged in via mobile app',
        );

        return $this->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'username' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
            ],
        ]);
    }
}
