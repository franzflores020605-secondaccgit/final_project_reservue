<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\AuditLogger;
use App\Service\DocumentImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/product')]
#[IsGranted('ROLE_USER')]
final class ProductController extends AbstractController
{
    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $products = ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF'))
            ? $productRepository->findAll()
            : $productRepository->findBy(['owner' => $this->getUser()]);

        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger, DocumentImageStorage $documentImageStorage): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyUploadedProductImage($form, $product, $documentImageStorage);
            $product->setOwner($this->getUser());
            $entityManager->persist($product);
            $entityManager->flush();
            $auditLogger->log('Create', 'Product', $product->getId(), sprintf('Created product "%s"', $product->getName()));

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product-staff/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        $this->assertOwnershipOrAdmin($product);
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, AuditLogger $auditLogger, DocumentImageStorage $documentImageStorage): Response
    {
        $this->assertOwnershipOrAdmin($product);
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyUploadedProductImage($form, $product, $documentImageStorage);
            $entityManager->flush();
            $auditLogger->log('Update', 'Product', $product->getId(), sprintf('Updated product "%s"', $product->getName()));

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager, AuditLogger $auditLogger, DocumentImageStorage $documentImageStorage): Response
    {
        $this->assertOwnershipOrAdmin($product);
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $name = $product->getName();
            $documentImageStorage->removeIfManaged($product->getImagePath());
            $entityManager->remove($product);
            $entityManager->flush();
            $auditLogger->log('Delete', 'Product', $product->getId(), sprintf('Deleted product "%s"', $name));
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    private function assertOwnershipOrAdmin(Product $product): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return;
        }
        if ($product->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only access your own products.');
        }
    }

    private function applyUploadedProductImage(FormInterface $form, Product $product, DocumentImageStorage $storage): void
    {
        $file = $form->get('imageFile')->getData();
        if ($file instanceof UploadedFile) {
            $storage->removeIfManaged($product->getImagePath());
            $product->setImagePath($storage->store($file));
        }
    }
}
