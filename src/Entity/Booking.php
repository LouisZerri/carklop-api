<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;
use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
    ],
    normalizationContext: ['groups' => ['booking:read']],
    denormalizationContext: ['groups' => ['booking:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'passenger' => 'exact',
    'trip' => 'exact',
])]
class Booking
{
    /* ============= ATTRIBUTS ============= */

    // === Identifiant principal ===
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?int $id = null;

    // === Relations principales ===
    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['booking:read', 'booking:write'])]
    #[Assert\NotBlank(message: 'Le trajet est obligatoire')]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['booking:read', 'booking:write'])]
    #[Assert\NotBlank(message: 'Le passager est obligatoire')]
    private ?User $passenger = null;

    // === Données de réservation (infos principales) ===
    #[ORM\Column]
    #[Groups(['booking:read', 'booking:write'])]
    #[Assert\NotBlank(message: 'Le nombre de places est obligatoire')]
    #[Assert\Range(min: 1, max: 8, notInRangeMessage: 'Entre 1 et 8 places')]
    private ?int $seatsBooked = null;

    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?int $pricePerSeat = null;

    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?int $commissionAmount = null;

    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?int $totalAmount = null;

    // === Paiement Stripe ===
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeTransferId = null;

    // === Dates/statut/historique ===
    #[ORM\Column(length: 20)]
    #[Groups(['booking:read'])]
    #[Assert\Choice(choices: ['pending', 'paid', 'completed', 'refunded', 'cancelled', 'failed'], message: 'Statut invalide')]
    private ?string $status = null;

    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['booking:read'])]
    private ?\DateTimeImmutable $refundedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['booking:read'])]
    private ?int $refundedAmount = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['booking:read'])]
    #[Assert\Choice(choices: ['passenger', 'driver', null], message: 'Valeur invalide')]
    private ?string $cancelledBy = null;

    // === Relations secondaires / autres infos ===

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'booking', orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\OneToOne(mappedBy: 'booking', cascade: ['persist', 'remove'])]
    private ?Conversation $conversation = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['booking:read', 'booking:write'])]
    private ?int $estimatedBudget = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['booking:read'])]
    private ?int $estimatedSavings = null;

    /* ============= CONSTRUCTEUR ============= */

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    /* ============= GETTERS / SETTERS ============= */

    // ---- Identifiant ----
    public function getId(): ?int
    {
        return $this->id;
    }

    // ---- Relations principales ----
    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;
        return $this;
    }

    public function getPassenger(): ?User
    {
        return $this->passenger;
    }

    public function setPassenger(?User $passenger): static
    {
        $this->passenger = $passenger;
        return $this;
    }

    // ---- Données de réservation ----
    public function getSeatsBooked(): ?int
    {
        return $this->seatsBooked;
    }

    public function setSeatsBooked(int $seatsBooked): static
    {
        $this->seatsBooked = $seatsBooked;
        return $this;
    }

    public function getPricePerSeat(): ?int
    {
        return $this->pricePerSeat;
    }

    public function setPricePerSeat(int $pricePerSeat): static
    {
        $this->pricePerSeat = $pricePerSeat;
        return $this;
    }

    public function getCommissionAmount(): ?int
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(int $commissionAmount): static
    {
        $this->commissionAmount = $commissionAmount;
        return $this;
    }

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    // ---- Paiement Stripe ----
    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
    }

    public function getStripeTransferId(): ?string
    {
        return $this->stripeTransferId;
    }

    public function setStripeTransferId(?string $stripeTransferId): static
    {
        $this->stripeTransferId = $stripeTransferId;
        return $this;
    }

    // ---- Dates/statut/historique ----
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getRefundedAt(): ?\DateTimeImmutable
    {
        return $this->refundedAt;
    }

    public function setRefundedAt(?\DateTimeImmutable $refundedAt): static
    {
        $this->refundedAt = $refundedAt;
        return $this;
    }

    public function getRefundedAmount(): ?int
    {
        return $this->refundedAmount;
    }

    public function setRefundedAmount(?int $refundedAmount): static
    {
        $this->refundedAmount = $refundedAmount;
        return $this;
    }

    public function getCancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?string $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;
        return $this;
    }

    // ---- Relations secondaires ----
    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setBooking($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getBooking() === $this) {
                $review->setBooking(null);
            }
        }
        return $this;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): static
    {
        // set the owning side of the relation if necessary
        if ($conversation->getBooking() !== $this) {
            $conversation->setBooking($this);
        }
        $this->conversation = $conversation;
        return $this;
    }

    // ---- Estimations (savings/budget) ----
    public function getEstimatedBudget(): ?int
    {
        return $this->estimatedBudget;
    }

    public function setEstimatedBudget(?int $estimatedBudget): static
    {
        $this->estimatedBudget = $estimatedBudget;
        return $this;
    }

    public function getEstimatedSavings(): ?int
    {
        return $this->estimatedSavings;
    }

    public function setEstimatedSavings(?int $estimatedSavings): static
    {
        $this->estimatedSavings = $estimatedSavings;
        return $this;
    }
}
