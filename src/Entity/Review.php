<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['review:read']],
)]
class Review
{
    // ==== Identifiant principal ====
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['review:read'])]
    private ?int $id = null;

    // ==== Relations ====
    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read'])]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(inversedBy: 'reviewsGiven')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read'])]
    private ?User $author = null;

    #[ORM\ManyToOne(inversedBy: 'reviewsReceived')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read'])]
    private ?User $target = null;

    // ==== Attributs ====
    #[ORM\Column]
    #[Assert\NotBlank(message: 'La note est obligatoire')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'Note entre 1 et 5')]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['review:read'])]
    #[Assert\Length(max: 1000, maxMessage: 'Maximum 1000 caractères')]
    private ?string $comment = null;

    #[ORM\Column]
    #[Groups(['review:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // ==== Getters & Setters : Identifiant principal ====
    public function getId(): ?int
    {
        return $this->id;
    }

    // ==== Getters & Setters : Relations ====
    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getTarget(): ?User
    {
        return $this->target;
    }

    public function setTarget(?User $target): static
    {
        $this->target = $target;
        return $this;
    }

    // ==== Getters & Setters : Attributs ====
    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
