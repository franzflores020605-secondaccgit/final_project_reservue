<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Forces the router host/scheme for OAuth redirect URLs on Railway.
 * Uses GOOGLE_REDIRECT_URI when set, otherwise DEFAULT_URI (must be your public https URL).
 */
final class DefaultUriRequestContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $defaultUri,
        private readonly string $googleRedirectUri = '',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 512]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $baseUri = trim($this->googleRedirectUri) !== '' ? trim($this->googleRedirectUri) : trim($this->defaultUri);
        if ($baseUri === '') {
            return;
        }

        // Skip only when we would force localhost without an explicit Google redirect URI
        if (str_contains($baseUri, 'localhost') && trim($this->googleRedirectUri) === '') {
            return;
        }

        $parts = parse_url($baseUri);
        if (!\is_array($parts) || empty($parts['host'])) {
            return;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $context = $this->router->getContext();
        $context->setHost($parts['host']);
        $context->setScheme($scheme);

        if (isset($parts['port'])) {
            if ($scheme === 'https') {
                $context->setHttpsPort((int) $parts['port']);
            } else {
                $context->setHttpPort((int) $parts['port']);
            }
        } elseif ($scheme === 'https') {
            $context->setHttpsPort(443);
        }
    }
}
