<?php

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Returns a mobile-friendly JSON body when JWT auth fails (expired/invalid token).
 */
final class MobileApiAuthenticationFailureSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 16],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/mobile/v1')) {
            return;
        }

        if (!$this->isAuthenticationFailure($event->getThrowable())) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'success' => false,
            'data' => null,
            'meta' => [],
            'error' => [
                'message' => 'Your session expired. Please sign out, sign in again, then submit your booking.',
            ],
        ], 401));
    }

    private function isAuthenticationFailure(\Throwable $throwable): bool
    {
        if ($throwable instanceof AuthenticationException || $throwable instanceof JWTDecodeFailureException) {
            return true;
        }

        $previous = $throwable->getPrevious();
        if ($previous instanceof \Throwable) {
            return $this->isAuthenticationFailure($previous);
        }

        return false;
    }
}
