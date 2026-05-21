<?php

namespace App\Form;

use App\Entity\CustomerPackageBooking;
use App\Entity\Product;
use App\Entity\TravelPackage;
use App\Repository\ProductRepository;
use App\Repository\TravelPackageRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerPackageBookingType extends AbstractType
{
    public function __construct(
        private readonly TravelPackageRepository $travelPackageRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $travelerChoices = array_combine(
            range(1, 12),
            range(1, 12)
        );

        /** @var list<int> $allowedPackageIds */
        $allowedPackageIds = $options['allowed_travel_package_ids'];
        /** @var list<int> $allowedProductIds */
        $allowedProductIds = $options['allowed_product_ids'];

        $builder
            ->add('packageId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'rv-package-id-input', 'autocomplete' => 'off'],
            ])
            ->add('productId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'rv-product-id-input', 'autocomplete' => 'off'],
            ])
            ->add('bookingContext', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'rv-booking-context-input', 'autocomplete' => 'off'],
            ])
            ->add('travelDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'attr' => ['class' => 'rv-modal-field-date'],
            ])
            ->add('numberOfTravelers', ChoiceType::class, [
                'choices' => $travelerChoices,
                'required' => true,
                'attr' => ['class' => 'rv-modal-field-travelers'],
            ])
            ->add('contactFirstName', TextType::class, [
                'label' => 'First name',
                'attr' => ['placeholder' => 'First name', 'autocomplete' => 'given-name'],
            ])
            ->add('contactLastName', TextType::class, [
                'label' => 'Last name',
                'attr' => ['placeholder' => 'Last name', 'autocomplete' => 'family-name'],
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'you@example.com', 'autocomplete' => 'email'],
            ])
            ->add('contactPhone', TelType::class, [
                'label' => 'Phone',
                'required' => false,
                'attr' => ['placeholder' => 'Optional', 'autocomplete' => 'tel'],
            ])
            ->add('contactPassportNumber', TextType::class, [
                'label' => 'Passport number',
                'attr' => ['placeholder' => 'As shown on passport', 'autocomplete' => 'off'],
            ])
            ->add('specialRequests', TextareaType::class, [
                'label' => 'Special requests',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Dietary needs, accessibility, celebration, etc.'],
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($allowedPackageIds, $allowedProductIds): void {
            $booking = $event->getData();
            if (!$booking instanceof CustomerPackageBooking) {
                return;
            }
            $form = $event->getForm();
            $ctx = (string) ($form->has('bookingContext') ? $form->get('bookingContext')->getData() : '');

            if ($ctx === 'inventory') {
                $booking->setTravelPackage(null);
                $rawProd = $form->get('productId')->getData();
                $productId = is_numeric($rawProd) ? (int) $rawProd : 0;
                if ($productId <= 0) {
                    $form->get('productId')->addError(new FormError('Choose a catalog item to complete this booking.'));

                    return;
                }
                $product = $this->productRepository->find($productId);
                if (!$product instanceof Product) {
                    $form->get('productId')->addError(new FormError('That catalog item is not available.'));

                    return;
                }
                if ($allowedProductIds !== [] && !\in_array($product->getId(), $allowedProductIds, true)) {
                    $form->get('productId')->addError(new FormError('That catalog item is not available for your current filters.'));

                    return;
                }
                if (!$product->isShowOnBookPage()) {
                    $form->get('productId')->addError(new FormError('That catalog item is not available.'));

                    return;
                }
                $booking->setBookedProduct($product);

                return;
            }

            $booking->setBookedProduct(null);

            $raw = $form->get('packageId')->getData();
            $id = is_numeric($raw) ? (int) $raw : 0;
            if ($id <= 0) {
                $form->get('packageId')->addError(new FormError('Choose a travel package to complete this booking.'));

                return;
            }
            $package = $this->travelPackageRepository->find($id);
            if (!$package instanceof TravelPackage || !$package->isPublished()) {
                $form->get('packageId')->addError(new FormError('That travel package is not available.'));

                return;
            }
            if ($allowedPackageIds !== [] && !\in_array($package->getId(), $allowedPackageIds, true)) {
                $form->get('packageId')->addError(new FormError('That travel package is not available for your current filters.'));

                return;
            }
            $booking->setTravelPackage($package);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerPackageBooking::class,
            'csrf_protection' => false,
            'allowed_travel_package_ids' => [],
            'allowed_product_ids' => [],
        ]);
        $resolver->setAllowedTypes('allowed_travel_package_ids', 'array');
        $resolver->setAllowedTypes('allowed_product_ids', 'array');
    }
}
