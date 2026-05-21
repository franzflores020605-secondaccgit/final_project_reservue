<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class GoogleAuthenticator extends OAuth2Authenticator
{
    

    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $clientFlag = null;
        if ($request->hasSession()) {
            $clientFlag = $request->getSession()->get('google_login_client');
            // One-time read; avoid stale flags affecting future logins.
            $request->getSession()->remove('google_login_client');
        }
        $ua = strtolower((string) $request->headers->get('user-agent', ''));
        $isMobileClient = ($clientFlag === 'mobile' || str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone'));
        $defaultRoleForNewGoogleUser = $isMobileClient ? 'ROLE_USER' : 'ROLE_STAFF';

        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);
        $email = (string) $googleUser->getEmail();

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email, $defaultRoleForNewGoogleUser, $isMobileClient) {
                /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
                // 1) Find or create user
                $repo = $this->entityManager->getRepository(User::class);
                $user = $repo->findOneBy(['email' => $email]) ?? $repo->findOneBy(['username' => $email]);

                if (!$user) {
                    $user = new User();
                    $user->setUsername($email);
                    $user->setEmail($email);
                    // Set a random password (they won't use it for Google login)
                    $user->setPassword(bin2hex(random_bytes(32)));
                    $user->setRoles([$defaultRoleForNewGoogleUser]);
                    $user->setIsVerified(true);
                    $user->setVerificationToken(null);

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                } else {
                    // If the staff/admin account was created with a non-email username,
                    // Google login should still find it via email and mark it verified.
                    if (null === $user->getEmail() || trim((string) $user->getEmail()) === '') {
                        $user->setEmail($email);
                    }
                    if (!$user->isVerified()) {
                        $user->setIsVerified(true);
                    }
                    if (null !== $user->getVerificationToken()) {
                        $user->setVerificationToken(null);
                    }

                    // Web Google login: ensure they land as staff unless already staff/admin.
                    // Mobile Google login: keep existing roles (we don't downgrade), but new users remain ROLE_USER.
                    if (!$isMobileClient) {
                        $roles = $user->getRoles();
                        if (!\in_array('ROLE_ADMIN', $roles, true) && !\in_array('ROLE_STAFF', $roles, true)) {
                            $user->setRoles(['ROLE_STAFF']);
                        }
                    }
                    $this->entityManager->flush();
                }

                return $user;
            })
            ,
            [new RememberMeBadge()]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // If this flow was started as mobile, always land on the customer area.
        $ua = strtolower((string) $request->headers->get('user-agent', ''));
        $isMobile = (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone'));
        if ($isMobile) {
            return new RedirectResponse($this->router->generate('app_customer_my_trips'));
        }

        $user = $token->getUser();
        if ($user instanceof User) {
            $roles = $user->getRoles();
            if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
                return new RedirectResponse($this->router->generate('app_staff_dashboard'));
            }
        }

        return new RedirectResponse($this->router->generate('app_customer_my_trips'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());
        $request->getSession()->set('_security.last_error', $exception);

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
