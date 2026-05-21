<?php

namespace App\Form;

use App\Entity\CustomerPackageBooking;
use App\Enum\CustomerBookingStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CustomerPackageBookingStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', EnumType::class, [
            'class' => CustomerBookingStatus::class,
            'label' => 'Booking status',
            'attr' => ['class' => 'form-control'],
            'choice_label' => static fn (CustomerBookingStatus $s): string => match ($s) {
                CustomerBookingStatus::Pending => 'Pending',
                CustomerBookingStatus::Completed => 'Completed',
                CustomerBookingStatus::Cancelled => 'Cancelled',
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerPackageBooking::class,
        ]);
    }
}
