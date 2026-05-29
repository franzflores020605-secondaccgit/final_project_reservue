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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Native app Google Sign-In (ID token). New users always receive ROLE_USER only.
 */
#[Route('/api/mobile/v1/auth', name: 'api_mobile_v1_auth_')]
final class MobileGoogleAuthController extends AbstractController
{
    /**
     * OAuth client IDs from android/app/google-services.json (web + Android).
     * ID token "aud" must match one of these (or GOOGLE_MOBILE_CLIENT_IDS on the server).
     */
    private const MOBILE_GOOGLE_CLIENT_IDS = [
        '765506474739-qdjd532nucuri220vc8gscf39l2ogu71.apps.googleusercontent.com',
        '765506474739-icjkn5uu90ph7t27allqbdritbvuap28.apps.googleusercontent.com',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly HttpClientInterface $httpClient,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('/google', name: 'google', methods: ['POST'])]
    public function google(Request $request): JsonResponse
    {
        try {
            return $this->handleGoogleSignIn($request);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Google sign-in could not be completed. Try username and password, or contact support.',
                'detail' => $this->getParameter('kernel.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function handleGoogleSignIn(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $idToken = is_array($payload) ? ($payload['idToken'] ?? $payload['id_token'] ?? null) : null;

        if (!is_string($idToken) || trim($idToken) === '') {
            return $this->json(['success' => false, 'message' => 'Google ID token is required.'], 400);
        }

        $tokenInfo = $this->verifyGoogleIdToken($idToken);
        if ($tokenInfo === null) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid Google token. Use the same Google account you chose in the app.',
            ], 401);
        }

        $email = strtolower(trim((string) ($tokenInfo['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['success' => false, 'message' => 'Google account has no valid email.'], 400);
        }

        $repo = $this->entityManager->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $email]) ?? $repo->findOneBy(['username' => $email]);

        if ($user instanceof User) {
            $roles = $user->getRoles();
            if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Staff and admin accounts must sign in on the ReserVue website, not the customer app.',
                ], 403);
            }
            if (null === $user->getEmail() || trim((string) $user->getEmail()) === '') {
                $user->setEmail($email);
            }
            if (!$user->isVerified()) {
                $user->setIsVerified(true);
                $user->setVerificationToken(null);
            }
            $this->entityManager->flush();
        } else {
            $user = new User();
            $user->setUsername($this->uniqueUsernameForEmail($email));
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(true);
            $user->setVerificationToken(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        try {
            $jwt = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Server login is not configured (JWT keys). Set JWT_PASSPHRASE on Railway and redeploy.',
                'detail' => $this->getParameter('kernel.debug') ? $e->getMessage() : null,
            ], 500);
        }

        try {
            $this->auditLogger->logForUser(
                $user,
                'Login',
                'Auth',
                $user->getId(),
                'User logged in via mobile app (Google)',
            );
        } catch (\Throwable) {
            // Login must succeed even if audit persistence fails.
        }

        return $this->json([
            'token' => $jwt,
            'user' => [
                'username' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo',
                ['query' => ['id_token' => $idToken]],
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
            if (!\is_array($data) || isset($data['error'])) {
                return null;
            }

            $aud = (string) ($data['aud'] ?? '');
            if ($aud === '' || !\in_array($aud, $this->allowedGoogleClientIds(), true)) {
                return null;
            }

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function allowedGoogleClientIds(): array
    {
        $mobileEnv = trim((string) ($_ENV['GOOGLE_MOBILE_CLIENT_IDS'] ?? $_SERVER['GOOGLE_MOBILE_CLIENT_IDS'] ?? getenv('GOOGLE_MOBILE_CLIENT_IDS') ?: ''));
        $extra = $mobileEnv !== ''
            ? array_map('trim', explode(',', $mobileEnv))
            : [];

        return array_values(array_unique(array_filter([
            ...self::MOBILE_GOOGLE_CLIENT_IDS,
            ...$extra,
        ])));
    }

    private function uniqueUsernameForEmail(string $email): string
    {
        $repo = $this->entityManager->getRepository(User::class);
        $base = $email;
        if ($repo->findOneBy(['username' => $base]) === null) {
            return $base;
        }

        $local = strstr($email, '@', true) ?: 'user';
        $candidate = $local;
        $n = 1;
        while ($repo->findOneBy(['username' => $candidate]) !== null) {
            $candidate = $local.$n;
            ++$n;
        }

        return $candidate;
    }
}
