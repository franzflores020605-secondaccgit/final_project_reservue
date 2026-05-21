<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/mobile/v1', name: 'api_mobile_v1_')]
final class MobileAccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/me', name: 'me_show', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        $actor = $this->resolveApiUser($user);
        if (!$actor instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        return $this->json($this->ok($this->serializeUser($actor)));
    }

    #[Route('/me', name: 'me_update', methods: ['PATCH'])]
    public function updateMe(
        Request $request,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $actor = $this->resolveApiUser($user);
        if (!$actor instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json($this->error('Invalid JSON body.'), 400);
        }

        if (isset($data['username']) && is_string($data['username'])) {
            $username = trim($data['username']);
            if ($username !== '') {
                $existing = $this->userRepository->findOneBy(['username' => $username]);
                if ($existing instanceof User && $existing->getId() !== $actor->getId()) {
                    return $this->json($this->error('Username already taken.'), 409);
                }
                $actor->setUsername($username);
            }
        }

        if (\array_key_exists('email', $data)) {
            $email = is_string($data['email']) ? trim($data['email']) : '';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json($this->error('Invalid email address.'), 400);
            }
            if ($email !== '') {
                $existing = $this->userRepository->findOneBy(['email' => $email]);
                if ($existing instanceof User && $existing->getId() !== $actor->getId()) {
                    return $this->json($this->error('Email already registered.'), 409);
                }
            }
            $actor->setEmail($email !== '' ? $email : null);
        }

        $errors = $this->validator->validate($actor);
        if (\count($errors) > 0) {
            return $this->json($this->error((string) $errors->get(0)->getMessage()), 400);
        }

        $this->entityManager->flush();

        return $this->json($this->ok($this->serializeUser($actor)));
    }

    #[Route('/me/password', name: 'me_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        $actor = $this->resolveApiUser($user);
        if (!$actor instanceof User) {
            return $this->json($this->error('Unauthenticated.'), 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json($this->error('Invalid JSON body.'), 400);
        }

        $current = (string) ($data['currentPassword'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');
        $confirm = (string) ($data['confirmPassword'] ?? $newPassword);

        if ($current === '' || $newPassword === '') {
            return $this->json($this->error('Current and new password are required.'), 400);
        }
        if ($newPassword !== $confirm) {
            return $this->json($this->error('New passwords do not match.'), 400);
        }
        if (\strlen($newPassword) < 8) {
            return $this->json($this->error('New password must be at least 8 characters.'), 400);
        }
        if (!$this->passwordHasher->isPasswordValid($actor, $current)) {
            return $this->json($this->error('Current password is incorrect.'), 400);
        }

        $actor->setPassword($this->passwordHasher->hashPassword($actor, $newPassword));
        $this->entityManager->flush();

        return $this->json($this->ok(['message' => 'Password updated successfully.']));
    }

    private function resolveApiUser(?User $user): ?User
    {
        if (!$user instanceof User) {
            return null;
        }
        if ($user->getId() !== null) {
            return $this->userRepository->find($user->getId())
                ?? $this->userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
        }

        return $this->userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'verified' => $user->isVerified(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{success: true, data: array<string, mixed>, meta: array{}, error: null}
     */
    private function ok(array $data): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => [],
            'error' => null,
        ];
    }

    /**
     * @return array{success: false, data: null, meta: array{}, error: array{message: string}}
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'data' => null,
            'meta' => [],
            'error' => ['message' => $message],
        ];
    }
}
