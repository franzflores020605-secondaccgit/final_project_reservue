<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
  
     #[Route('/connect/google', name: 'connect_google')]
    
    public function connect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        // Mark whether this OAuth flow was initiated by a mobile client.
        // Mobile can pass ?client=mobile, and we also fall back to a basic UA check.
        $client = strtolower(trim((string) $request->query->get('client', '')));
        $ua = strtolower((string) $request->headers->get('user-agent', ''));
        $isMobile = ($client === 'mobile' || $client === 'app' || str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone'));

        if ($request->hasSession()) {
            $request->getSession()->set('google_login_client', $isMobile ? 'mobile' : 'web');
        }

        // Redirect to Google OAuth
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email',
                'profile'
            ]);
    }

 
     #[Route('/connect/google/check', name: 'connect_google_check')]
     
    public function connectCheck(): never
    {
        // This controller is never executed - it's intercepted by the GoogleAuthenticator
        // return $this->redirectToRoute('app_landing');
    }
}
