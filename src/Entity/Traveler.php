<?php

namespace App\Entity;

use App\Repository\TravelerRepository;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TravelerRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['traveler:read']],
    denormalizationContext: ['groups' => ['traveler:write']]
)]
class Traveler
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['traveler:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['traveler:read', 'traveler:write'])]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z\s\.,\-\/\'\"!?]*$/',
        message: 'First Name can only contain letters, spaces, and basic punctuation'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Groups(['traveler:read', 'traveler:write'])]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z\s\.,\-\/\'\"!?]*$/',
        message: 'Last Name can only contain letters, spaces, and basic punctuation'
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['traveler:read', 'traveler:write'])]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s\.,\-\/\'\"!?]*$/',
        message: 'Passport Number can only contain letters, numbers, spaces, and basic punctuation'
    )]
    private ?string $passportNumber = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['traveler:read', 'traveler:write'])]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['traveler:read', 'traveler:write'])]
    #[Assert\Length(max: 64)]
    private ?string $phone = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPassportNumber(): ?string
    {
        return $this->passportNumber;
    }

    public function setPassportNumber(?string $passportNumber): static
    {
        $this->passportNumber = $passportNumber;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }
}
