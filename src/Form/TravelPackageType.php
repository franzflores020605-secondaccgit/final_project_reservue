<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\TravelPackage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class TravelPackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('durationLabel', TextType::class, [
                'label' => 'Duration label',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('pricePerPerson', MoneyType::class, [
                'label' => 'Fallback price (per person)',
                'currency' => 'PHP',
                'help' => 'Philippine Peso. Used only when no included products are selected; otherwise the total comes from those products.',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Package photo',
                'mapped' => false,
                'required' => false,
                'help' => 'Choose an image from your computer. It is saved under Documents — no folder or file name to type.',
                'constraints' => [
                    new Image(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Please upload a JPEG, PNG, WebP, or GIF image.',
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                ],
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Published on catalog',
                'required' => false,
                'help' => 'When checked, this package can appear on the public Book page (requires photo and category).',
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Category',
                'placeholder' => 'Select a category',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('products', EntityType::class, [
                'class' => Product::class,
                'choice_label' => fn (Product $p) => sprintf(
                    '%s — ₱%s (%s)',
                    $p->getName(),
                    number_format((float) $p->getPrice(), 2, '.', ','),
                    $p->getCategory()?->getName() ?? '—'
                ),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Included products',
                'help' => 'Prices and descriptions shown on the site are read from each product record (live sync).',
                'query_builder' => fn ($repository) => $repository->createQueryBuilder('p')
                    ->leftJoin('p.category', 'c')
                    ->addSelect('c')
                    ->orderBy('c.name', 'ASC')
                    ->addOrderBy('p.name', 'ASC'),
                'attr' => ['class' => 'travel-package-product-checkboxes'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TravelPackage::class,
        ]);
    }
}
