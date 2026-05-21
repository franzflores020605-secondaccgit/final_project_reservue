<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\CustomerPackageBooking;
use App\Entity\User;
use App\Form\CustomerPackageBookingType;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\TravelPackageRepository;
use App\Service\CustomerBookingSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class CustomerBookingController extends AbstractController
{
    use TargetPathTrait;

    private const MAIN_FIREWALL = 'main';

    #[Route('/book', name: 'app_customer_booking_index', methods: ['GET', 'POST'])]
    #[Route('/customer-booking', name: 'app_customer_booking_portal', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TravelPackageRepository $travelPackageRepository,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        CustomerBookingSubmissionService $customerBookingSubmissionService,
        LoggerInterface $logger,
    ): Response {
        if (!$this->getUser()) {
            if ($request->hasSession()) {
                $this->saveTargetPath($request->getSession(), self::MAIN_FIREWALL, $request->getRequestUri());
            }
            $this->addFlash(
                'booking_info',
                'Please register (or sign in) to browse packages and submit a booking. After you sign in, we will return you to this page.'
            );

            return $this->redirectToRoute('app_landing', ['register' => '1']);
        }

        $categoryParam = $request->query->get('category');
        $budgetParam = $request->query->get('budget');
        $qParam = $request->query->get('q');

        $categoryFilter = null;
        if (is_string($categoryParam) && ctype_digit($categoryParam)) {
            $categoryFilter = $categoryRepository->find((int) $categoryParam);
        }

        [$minPrice, $maxPrice] = $this->resolveBudgetRange(is_string($budgetParam) ? $budgetParam : null);

        $packages = $travelPackageRepository->findPublishedForCatalog();
        $packages = $this->filterPackagesForCatalog($packages, $categoryFilter, $minPrice, $maxPrice);
        $packages = $this->filterPackagesForQueryOrFallback(
            $packages,
            is_string($qParam) ? $qParam : null
        );

        $bookProducts = $this->filterProductsForCatalog(
            $productRepository->findEligibleForBookPage(),
            $categoryFilter,
            $minPrice,
            $maxPrice
        );

        $categories = $categoryRepository->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $booking = new CustomerPackageBooking();
        $allowedPackageIds = array_values(array_map(
            static fn ($p) => $p->getId(),
            $packages
        ));
        $allowedProductIds = array_values(array_map(
            static fn ($p) => $p->getId(),
            $bookProducts
        ));
        $form = $this->createForm(CustomerPackageBookingType::class, $booking, [
            'allowed_travel_package_ids' => $allowedPackageIds,
            'allowed_product_ids' => $allowedProductIds,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $submitter = $this->getUser();
                    $codes = $customerBookingSubmissionService->persistLeadTravelerAndBooking(
                        $booking,
                        $submitter instanceof User ? $submitter : null
                    );
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('booking_error', $e->getMessage());

                    $preserveQuery = array_filter([
                        'category' => $categoryParam,
                        'budget' => $budgetParam,
                        'q' => $qParam,
                    ], static fn ($v) => $v !== null && $v !== '');

                    return $this->render('customer_booking/index.html.twig', [
                        'packages' => $packages,
                        'book_products' => $bookProducts,
                        'form' => $form,
                        'filter_category' => $categoryParam,
                        'filter_budget' => $budgetParam,
                        'preserve_query' => $preserveQuery,
                        'booking_categories' => $categories,
                        'form_submitted_invalid' => true,
                    ]);
                } catch (\Throwable $e) {
                    $logger->error('Customer booking submission failed', [
                        'exception' => $e,
                    ]);
                    $message = $this->getParameter('kernel.debug')
                        ? 'Booking could not be saved: '.$e->getMessage()
                        : 'We could not save your booking. Please try again in a moment.';
                    $this->addFlash('booking_error', $message);

                    $preserveQuery = array_filter([
                        'category' => $categoryParam,
                        'budget' => $budgetParam,
                        'q' => $qParam,
                    ], static fn ($v) => $v !== null && $v !== '');

                    return $this->render('customer_booking/index.html.twig', [
                        'packages' => $packages,
                        'book_products' => $bookProducts,
                        'form' => $form,
                        'filter_category' => $categoryParam,
                        'filter_budget' => $budgetParam,
                        'preserve_query' => $preserveQuery,
                        'booking_categories' => $categories,
                        'form_submitted_invalid' => true,
                    ]);
                }

                $this->addFlash('booking_success', sprintf(
                    'Thank you! Your request is submitted for approval. Reference: %s. Our team will confirm your booking soon.',
                    $codes['referenceCode']
                ));

                $query = array_filter([
                    'category' => $categoryParam,
                    'budget' => $budgetParam,
                    'q' => $qParam,
                ], static fn ($v) => $v !== null && $v !== '');

                return $this->redirectToRoute('app_customer_booking_index', $query, Response::HTTP_SEE_OTHER);
            }

            $this->addFlash('booking_error', 'Please check the booking form — some information is missing or invalid.');
        }

        $preserveQuery = array_filter([
            'category' => $categoryParam,
            'budget' => $budgetParam,
            'q' => $qParam,
        ], static fn ($v) => $v !== null && $v !== '');

        return $this->render('customer_booking/index.html.twig', [
            'packages' => $packages,
            'book_products' => $bookProducts,
            'form' => $form,
            'filter_category' => $categoryParam,
            'filter_budget' => $budgetParam,
            'filter_q' => $qParam,
            'preserve_query' => $preserveQuery,
            'booking_categories' => $categories,
            'form_submitted_invalid' => $form->isSubmitted() && !$form->isValid(),
        ]);
    }

    /**
     * @param iterable<\App\Entity\TravelPackage> $packages
     *
     * @return list<\App\Entity\TravelPackage>
     */
    private function filterPackagesForCatalog(iterable $packages, ?Category $categoryFilter, ?float $minPrice, ?float $maxPrice): array
    {
        $out = [];
        foreach ($packages as $pkg) {
            if ($categoryFilter instanceof Category) {
                $cid = $pkg->getCategory()?->getId();
                if ($cid !== $categoryFilter->getId()) {
                    continue;
                }
            }

            $price = $pkg->getPricePerPersonFloat();
            if ($minPrice !== null && $price < $minPrice) {
                continue;
            }
            if ($maxPrice !== null && $price > $maxPrice) {
                continue;
            }

            $out[] = $pkg;
        }

        return $out;
    }

    /**
     * @param iterable<\App\Entity\Product> $products
     *
     * @return list<\App\Entity\Product>
     */
    private function filterProductsForCatalog(iterable $products, ?Category $categoryFilter, ?float $minPrice, ?float $maxPrice): array
    {
        $out = [];
        foreach ($products as $product) {
            if ($categoryFilter instanceof Category) {
                $cid = $product->getCategory()?->getId();
                if ($cid !== $categoryFilter->getId()) {
                    continue;
                }
            }

            $price = $product->getPrice();
            if ($price !== null) {
                if ($minPrice !== null && $price < $minPrice) {
                    continue;
                }
                if ($maxPrice !== null && $price > $maxPrice) {
                    continue;
                }
            } elseif ($minPrice !== null || $maxPrice !== null) {
                continue;
            }

            $out[] = $product;
        }

        return $out;
    }

    /**
     * @param iterable<\App\Entity\TravelPackage> $packages
     *
     * @return list<\App\Entity\TravelPackage>
     */
    private function filterPackagesForQueryOrFallback(iterable $packages, ?string $q): array
    {
        $all = [];
        foreach ($packages as $pkg) {
            $all[] = $pkg;
        }

        $q = is_string($q) ? trim($q) : '';
        if ($q === '') {
            return $all;
        }

        $needle = mb_strtolower($q);
        $matches = [];

        foreach ($all as $pkg) {
            $haystacks = [
                (string) $pkg->getName(),
                (string) ($pkg->getShortDescription() ?? ''),
                (string) ($pkg->getCategory()?->getName() ?? ''),
            ];

            foreach ($pkg->getProducts() as $product) {
                $haystacks[] = (string) ($product->getName() ?? '');
            }

            $ok = false;
            foreach ($haystacks as $h) {
                $h = trim($h);
                if ($h === '') {
                    continue;
                }
                if (mb_stripos(mb_strtolower($h), $needle) !== false) {
                    $ok = true;
                    break;
                }
            }

            if ($ok) {
                $matches[] = $pkg;
            }
        }

        if ($matches !== []) {
            return $matches;
        }

        // Fallback: show most affordable published packages (interpreted as "available").
        usort($all, static fn ($a, $b) => $a->getPricePerPersonFloat() <=> $b->getPricePerPersonFloat());
        $this->addFlash('booking_info', sprintf(
            'No packages matched "%s". Showing our most affordable available trips instead.',
            $q
        ));

        return $all;
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function resolveBudgetRange(?string $key): array
    {
        return match ($key) {
            'economy' => [0.0, 999.99],
            'moderate' => [1000.0, 2499.99],
            'premium' => [2500.0, null],
            default => [null, null],
        };
    }
}
