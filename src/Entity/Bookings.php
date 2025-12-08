<?php

namespace App\Entity;

use App\Enum\BookingStatus;
use Doctrine\DBAL\Types\Types;
use App\Repository\BookingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingsRepository::class)]
class Bookings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Booking ID is required')]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'Booking ID can only contain numbers (no letters or special characters allowed)'
    )]
    #[Assert\Positive(message: 'Only positive or valid ID number is required')]
    private ?string $bookingId = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Booking code is required')]
    private ?string $bookingCode = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: 'Booking date is required')]
    private ?\DateTimeInterface $bookingDate = null;

    #[ORM\Column(enumType: BookingStatus::class, length: 50)]
    #[Assert\NotNull(message: 'Booking status is required')]
    private ?BookingStatus $booking_status = null;

    #[ORM\Column(length: 250)]
    #[Assert\NotBlank(message: 'Booking type is required')]
    private ?string $booking_type = null;

    public function __construct()
    {
        $this->bookingDate = new \DateTime();
        $this->booking_status = BookingStatus::Pending; 
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBookingId(): ?string
    {
        return $this->bookingId;
    }

    public function setBookingId(string $bookingId): static
    {
        $this->bookingId = $bookingId;
        return $this;
    }

    public function getBookingCode(): ?string
    {
        return $this->bookingCode;
    }

    public function setBookingCode(string $bookingCode): static
    {
        $this->bookingCode = $bookingCode;
        return $this;
    }

    public function getBookingDate(): ?\DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeInterface $bookingDate): static
    {
        $this->bookingDate = $bookingDate;
        return $this;
    }

    public function getBookingStatus(): ?BookingStatus
    {
        return $this->booking_status;
    }

    public function setBookingStatus(BookingStatus $booking_status): static
    {
        $this->booking_status = $booking_status;
        return $this;
    }

    public function getBookingType(): ?string
    {
        return $this->booking_type;
    }

    public function setBookingType(?string $booking_type): static
    {
        $this->booking_type = $booking_type;
        return $this;
    }

    public function getBookingStatusString(): ?string
    {   
        return $this->booking_status?->value;
    }
}