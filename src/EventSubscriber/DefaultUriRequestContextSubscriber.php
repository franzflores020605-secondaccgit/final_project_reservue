<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Ensures generated URLs (e.g. Google OAuth redirect_uri) use DEFAULT_URI on Railway,
 * not an internal/wrong host from the reverse proxy.
 */
final class DefaultUriRequestContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $defaultUri,
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

        $defaultUri = trim($this->defaultUri);
        if ($defaultUri === '' || str_contains($defaultUri, 'localhost')) {
            return;
        }

        $parts = parse_url($defaultUri);
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
        }
    }
}
