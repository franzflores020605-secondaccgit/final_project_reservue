<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Native app Google Sign-In (ID token). New users always receive ROLE_USER only.
 */
#[Route('/api/mobile/v1/auth', name: 'api_mobile_v1_auth_')]
final class MobileGoogleAuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private string $googleClientId,
    ) {}

    #[Route('/google', name: 'google', methods: ['POST'])]
    public function google(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $idToken = is_array($payload) ? ($payload['idToken'] ?? $payload['id_token'] ?? null) : null;

        if (!is_string($idToken) || trim($idToken) === '') {
            return $this->json(['success' => false, 'message' => 'Google ID token is required.'], 400);
        }

        $tokenInfo = $this->verifyGoogleIdToken($idToken);
        if ($tokenInfo === null) {
            return $this->json(['success' => false, 'message' => 'Invalid Google token.'], 401);
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
            $user->setUsername($email);
            $user->setEmail($email);
            $user->setPassword(bin2hex(random_bytes(32)));
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(true);
            $user->setVerificationToken(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $jwt = $this->jwtManager->create($user);

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
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token='.urlencode($idToken);
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || isset($data['error'])) {
            return null;
        }

        $aud = (string) ($data['aud'] ?? '');
        $allowed = array_values(array_unique(array_filter([
            $this->googleClientId,
            '765506474739-qdjd532nucuri220vc8gscf39l2ogu71.apps.googleusercontent.com',
            '765506474739-49b3mr4tn4215rlk8nfdlm6mp0ftjs11.apps.googleusercontent.com',
        ])));

        if ($aud === '' || !\in_array($aud, $allowed, true)) {
            return null;
        }

        return $data;
    }
}
