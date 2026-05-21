<?php

namespace App\Entity;

use App\Repository\TravelPackageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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


#[ORM\Entity(repositoryClass: TravelPackageRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['travel_package:read']],
    denormalizationContext: ['groups' => ['travel_package:write']]
)]
class TravelPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['travel_package:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Groups(['travel_package:read', 'travel_package:write'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['travel_package:read', 'travel_package:write'])]
    private ?string $shortDescription = null;

    /**
     * Web path under /public (uploaded via Documents, e.g. uploads/documents/….jpg, or legacy theme paths).
     */
    #[ORM\Column(length: 500)]
    #[Assert\Length(max: 500)]
    #[Groups(['travel_package:read', 'travel_package:write'])]
    private ?string $imagePath = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Groups(['travel_package:read', 'travel_package:write'])]
    private ?string $durationLabel = null;

    /**
     * Admin/staff-set per-person price for the package.
     * Included products are informational only and do not affect this price.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\Positive]
    #[Groups(['travel_package:read', 'travel_package:write'])]
    private ?string $pricePerPerson = null;

    #[ORM\ManyToOne(inversedBy: 'travelPackages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['travel_package:read', 'travel_package:write'])]
    private ?Category $category = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, inversedBy: 'travelPackages')]
    #[ORM\JoinTable(name: 'package_products')]
    #[ORM\JoinColumn(name: 'package_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['travel_package:read', 'travel_package:write'])]
    private Collection $products;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['travel_package:read', 'travel_package:write'])]

    private bool $isPublished = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['travel_package:read'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    /**
     * Hero image for catalog cards: first linked product with an image, else the package image.
     */
    public function getCardImagePath(): string
    {
        foreach ($this->products as $product) {
            $p = $product->getImagePath();
            if ($p !== null && $p !== '') {
                return $p;
            }
        }

        return (string) $this->imagePath;
    }

    public function getDurationLabel(): ?string
    {
        return $this->durationLabel;
    }

    public function setDurationLabel(string $durationLabel): static
    {
        $this->durationLabel = $durationLabel;

        return $this;
    }

    public function getPricePerPerson(): ?string
    {
        return $this->pricePerPerson;
    }

    public function setPricePerPerson(string|float $pricePerPerson): static
    {
        $this->pricePerPerson = (string) $pricePerPerson;

        return $this;
    }

    /**
     * Backwards-compatible accessor used across templates/controllers.
     * Price is always the package's own {@see $pricePerPerson} (independent from included products).
     */
    public function getEffectivePricePerPerson(): float
    {
        return (float) $this->pricePerPerson;
    }

    public function getPricePerPersonFloat(): float
    {
        return (float) $this->pricePerPerson;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
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
}
