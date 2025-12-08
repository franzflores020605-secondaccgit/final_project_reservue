<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandingPageController extends AbstractController
{
    #[Route('/landing_page', name: 'app_landing_page')]
    // #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('landing_page/index.html.twig', [
            'controller_name' => 'LandingPageController',
        ]);
    }
}
