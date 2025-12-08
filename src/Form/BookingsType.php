<?php

namespace App\Form;

use App\Entity\Bookings;
use App\Enum\BookingStatus;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

class BookingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('booking_id')
            ->add('booking_code')
            ->add('booking_date', DateTimeType::class, [
                     'widget' => 'single_text',
                 ])
            ->add('booking_status', ChoiceType::class, [
                    'choices' => [
                        'Pending' => BookingStatus::Pending,
                        'Confirmed' => BookingStatus::Confirmed,
                        'Cancelled' => BookingStatus::Cancelled,
                    ],

                        'placeholder' => 'Select Booking Status',
                        'required' => true,
                        'choice_label'=> function($choice){
                            return ucfirst(strtolower($choice->value));
                        }
                ])
            ->add('booking_type', ChoiceType::class, [
                        'choices' => [
                        'Flight' => 'flight',
                        'Tour' => 'tour',
                        'Package' => 'package',
                ],
                        'placeholder' => 'Select Booking Type',
                ])
                 ;
                }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bookings::class,
        ]);
    }
}
