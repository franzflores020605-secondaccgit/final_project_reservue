<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProductRepository;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(ProductRepository $productRepository): Response
    {

        $products = $productRepository->findAll();

        return $this->render('dashboard/index.html.twig', [
            'products' => $products,
        ]);
    }
}
