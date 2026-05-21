<?php

namespace App\Entity;

use App\Enum\CustomerBookingStatus;
use App\Repository\CustomerPackageBookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Entity\Product;
use App\Entity\TravelPackage;
use App\Entity\Traveler;

#[ORM\Entity(repositoryClass: CustomerPackageBookingRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['customer_package_booking:read']],
    denormalizationContext: ['groups' => ['customer_package_booking:write']]
)]
class CustomerPackageBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['customer_package_booking:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?TravelPackage $travelPackage = null;

    /**
     * Set when the customer books an independent catalog product (inventory / add-on), not a travel package.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?Product $bookedProduct = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual('today')]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?\DateTimeInterface $travelDate = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 20)]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?int $numberOfTravelers = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z\s\.,\-\/\'\"!?]*$/',
        message: 'First name can only contain letters, spaces, and basic punctuation'
    )]
    private ?string $contactFirstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z\s\.,\-\/\'\"!?]*$/',
        message: 'Last name can only contain letters, spaces, and basic punctuation'
    )]
    private ?string $contactLastName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s\.,\-\/\'\"!?]*$/',
        message: 'Passport number can only contain letters, numbers, spaces, and basic punctuation'
    )]
    private ?string $contactPassportNumber = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    #[Assert\Length(max: 180)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?string $contactPhone = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?Traveler $leadTraveler = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?User $submittedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private ?string $specialRequests = null;

    #[ORM\Column(length: 32, unique: true)]
    #[Groups(['customer_package_booking:read'])]
    private ?string $referenceCode = null;

    /**
     * Derived category label for admin list (e.g. flight vs package) from the booked travel package.
     */
    #[ORM\Column(length: 32)]
    #[Groups(['customer_package_booking:read'])]
    private string $bookingKind = 'package';

    #[ORM\Column(enumType: CustomerBookingStatus::class, length: 32)]
    #[Groups(['customer_package_booking:read', 'customer_package_booking:write'])]
    private CustomerBookingStatus $status = CustomerBookingStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['customer_package_booking:read'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * When true, linked product quantities were reduced for this booking and can be restored on cancel/delete.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['customer_package_booking:read'])]
    private bool $inventoryDeducted = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = CustomerBookingStatus::Pending;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTravelPackage(): ?TravelPackage
    {
        return $this->travelPackage;
    }

    public function setTravelPackage(?TravelPackage $travelPackage): static
    {
        $this->travelPackage = $travelPackage;

        return $this;
    }

    public function getBookedProduct(): ?Product
    {
        return $this->bookedProduct;
    }

    public function setBookedProduct(?Product $bookedProduct): static
    {
        $this->bookedProduct = $bookedProduct;

        return $this;
    }

    /**
     * Human-readable trip line for customer UI (package name or standalone product name).
     */
    public function getDisplayTitle(): string
    {
        if ($this->travelPackage) {
            return (string) $this->travelPackage->getName();
        }
        if ($this->bookedProduct) {
            return (string) $this->bookedProduct->getName();
        }

        $legacy = $this->parseLegacyCatalogTitleFromNotes();

        return $legacy !== '' ? $legacy : 'Reservation';
    }

    /**
     * Public path under /public for card/hero imagery (package or catalog product).
     */
    public function getSummaryImagePath(): ?string
    {
        if ($this->travelPackage) {
            $card = trim((string) $this->travelPackage->getCardImagePath());
            if ($card !== '') {
                return $card;
            }
            $hero = $this->travelPackage->getImagePath();
            $heroTrim = $hero !== null ? trim((string) $hero) : '';

            return $heroTrim !== '' ? $heroTrim : null;
        }
        if ($this->bookedProduct) {
            $ip = $this->bookedProduct->getImagePath();
            $ipTrim = $ip !== null ? trim((string) $ip) : '';
            if ($ipTrim !== '') {
                return $ipTrim;
            }

            return null;
        }

        return null;
    }

    /**
     * Badge / label: travel package vs catalog item (incl. legacy catalog rows parsed from notes).
     */
    public function getReservationKindLabel(): string
    {
        if ($this->travelPackage !== null) {
            return 'Travel package';
        }
        if ($this->bookedProduct !== null) {
            return 'Catalog product';
        }
        if ($this->parseLegacyCatalogTitleFromNotes() !== '') {
            return 'Catalog product';
        }

        return 'Booking';
    }

    /**
     * Subtitle for profile cards: duration + category, or product category.
     */
    public function getCustomerSummaryDetailLine(): ?string
    {
        if ($this->travelPackage !== null) {
            $parts = array_filter([
                $this->travelPackage->getDurationLabel(),
                $this->travelPackage->getCategory()?->getName(),
            ]);

            return $parts !== [] ? implode(' · ', $parts) : null;
        }
        if ($this->bookedProduct !== null) {
            $cn = $this->bookedProduct->getCategory()?->getName();

            return $cn !== null && $cn !== '' ? 'Add-on · '.$cn : 'Catalog add-on';
        }

        return null;
    }

    /**
     * Price line for UI: per person vs per item × travelers.
     */
    public function getPriceDescriptionLine(): ?string
    {
        $n = (int) ($this->numberOfTravelers ?? 0);
        if ($n < 1) {
            return null;
        }
        if ($this->travelPackage !== null) {
            $pp = $this->travelPackage->getPricePerPersonFloat();

            return sprintf('₱%s / person × %d', number_format($pp, 2, '.', ','), $n);
        }
        if ($this->bookedProduct !== null && $this->bookedProduct->getPrice() !== null) {
            $pu = (float) $this->bookedProduct->getPrice();

            return sprintf('₱%s / item × %d', number_format($pu, 2, '.', ','), $n);
        }

        return null;
    }

    private function parseLegacyCatalogTitleFromNotes(): string
    {
        $s = (string) ($this->specialRequests ?? '');
        if ($s === '') {
            return '';
        }
        if (preg_match('/Interested in catalog item:\s*([^\n]+)/iu', $s, $m)) {
            $line = trim($m[1]);
            $line = preg_replace('/\s*\(product\s*#\d+\)\.?\s*$/i', '', $line) ?? $line;
            $line = trim($line);

            return $line !== '' ? $line : '';
        }

        return '';
    }

    public function getTravelDate(): ?\DateTimeInterface
    {
        return $this->travelDate;
    }

    public function setTravelDate(\DateTimeInterface $travelDate): static
    {
        $this->travelDate = $travelDate;

        return $this;
    }

    public function getNumberOfTravelers(): ?int
    {
        return $this->numberOfTravelers;
    }

    public function setNumberOfTravelers(int $numberOfTravelers): static
    {
        $this->numberOfTravelers = $numberOfTravelers;

        return $this;
    }

    public function getContactFirstName(): ?string
    {
        return $this->contactFirstName;
    }

    public function setContactFirstName(string $contactFirstName): static
    {
        $this->contactFirstName = $contactFirstName;

        return $this;
    }

    public function getContactLastName(): ?string
    {
        return $this->contactLastName;
    }

    public function setContactLastName(string $contactLastName): static
    {
        $this->contactLastName = $contactLastName;

        return $this;
    }

    public function getContactPassportNumber(): ?string
    {
        return $this->contactPassportNumber;
    }

    public function setContactPassportNumber(string $contactPassportNumber): static
    {
        $this->contactPassportNumber = $contactPassportNumber;

        return $this;
    }

    /**
     * Display name for staff lists (legacy single-field equivalent).
     */
    public function getContactDisplayName(): string
    {
        return trim(($this->contactFirstName ?? '').' '.($this->contactLastName ?? ''));
    }

    public function getLeadTraveler(): ?Traveler
    {
        return $this->leadTraveler;
    }

    public function setLeadTraveler(?Traveler $leadTraveler): static
    {
        $this->leadTraveler = $leadTraveler;

        return $this;
    }

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): static
    {
        $this->submittedBy = $submittedBy;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function getSpecialRequests(): ?string
    {
        return $this->specialRequests;
    }

    public function setSpecialRequests(?string $specialRequests): static
    {
        $this->specialRequests = $specialRequests;

        return $this;
    }

    public function getReferenceCode(): ?string
    {
        return $this->referenceCode;
    }

    public function setReferenceCode(string $referenceCode): static
    {
        $this->referenceCode = $referenceCode;

        return $this;
    }

    public function getBookingKind(): string
    {
        return $this->bookingKind;
    }

    public function setBookingKind(string $bookingKind): static
    {
        $this->bookingKind = $bookingKind;

        return $this;
    }

    public function getStatus(): CustomerBookingStatus
    {
        return $this->status;
    }

    public function setStatus(CustomerBookingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Completed and cancelled bookings are frozen; status cannot be changed again.
     */
    public function isStatusLocked(): bool
    {
        return \in_array($this->status, [CustomerBookingStatus::Completed, CustomerBookingStatus::Cancelled], true);
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isInventoryDeducted(): bool
    {
        return $this->inventoryDeducted;
    }

    public function setInventoryDeducted(bool $inventoryDeducted): static
    {
        $this->inventoryDeducted = $inventoryDeducted;

        return $this;
    }

    public function getEstimatedTotal(): float
    {
        $n = (int) ($this->numberOfTravelers ?? 0);
        if ($n < 1) {
            return 0.0;
        }
        if ($this->travelPackage) {
            return $this->travelPackage->getPricePerPersonFloat() * $n;
        }
        if ($this->bookedProduct && $this->bookedProduct->getPrice() !== null) {
            return (float) $this->bookedProduct->getPrice() * $n;
        }

        return 0.0;
    }
}
