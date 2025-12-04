<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\TripRepository;
use App\State\TripStateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: TripStateProcessor::class),
        new Put(processor: TripStateProcessor::class),
    ],
    normalizationContext: ['groups' => ['trip:read']],
    denormalizationContext: ['groups' => ['trip:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'departureCity' => 'partial',
    'destinationCity' => 'partial',
    'departureCountry' => 'exact',
    'destinationCountry' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['departureAt'])]
#[ApiFilter(OrderFilter::class, properties: ['departureAt', 'pricePerSeat', 'createdAt'])]
class Trip
{
    // ==== Identifiant principal ====
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['trip:read', 'booking:read'])]
    private ?int $id = null;

    // ==== Relations ====
    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['trip:read', 'trip:write'])]
    private ?User $driver = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'trip', orphanRemoval: true)]
    private Collection $bookings;

    // ==== Attributs ====
    #[ORM\Column(length: 100)]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\NotBlank(message: 'La ville de départ est obligatoire')]
    private ?string $departureCity = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    private ?string $departureAddress = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\Length(max: 2, maxMessage: 'Code pays sur 2 caractères (FR, BE...)')]
    private ?string $departureCountry = null;

    #[ORM\Column(length: 100)]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\NotBlank(message: 'La ville de destination est obligatoire')]
    private ?string $destinationCity = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    private ?string $destinationAddress = null;

    #[ORM\Column(length: 20)]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\NotBlank(message: 'Le pays de destination est obligatoire')]
    #[Assert\Length(max: 2, maxMessage: 'Code pays sur 2 caractères (DE, IT...)')]
    private ?string $destinationCountry = null;

    #[ORM\Column]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\NotBlank(message: 'La date de départ est obligatoire')]
    private ?\DateTimeImmutable $departureAt = null;

    #[ORM\Column]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\NotBlank(message: 'La date de retour est obligatoire')]
    #[Assert\GreaterThan(propertyPath: 'departureAt', message: 'Le retour doit être après le départ')]
    private ?\DateTimeImmutable $returnAt = null;

    #[ORM\Column]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\NotBlank(message: 'Le nombre de places est obligatoire')]
    #[Assert\Range(min: 1, max: 8, notInRangeMessage: 'Entre 1 et 8 places')]
    private ?int $availableSeats = null;

    #[ORM\Column]
    #[Groups(['trip:read', 'trip:write', 'booking:read'])]
    #[Assert\NotBlank(message: 'Le prix est obligatoire')]
    #[Assert\Positive(message: 'Le prix doit être positif')]
    private ?int $pricePerSeat = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['trip:read', 'trip:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Groups(['trip:read', 'trip:write'])]
    #[Assert\Choice(choices: ['draft', 'published', 'completed', 'cancelled'], message: 'Statut invalide')]
    private ?string $status = null;

    #[ORM\Column]
    #[Groups(['trip:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // ==== Constructeur ====
    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    // ==== Getters & Setters : Identifiant principal ====
    public function getId(): ?int
    {
        return $this->id;
    }

    // ==== Getters & Setters : Relations ====
    public function getDriver(): ?User
    {
        return $this->driver;
    }

    public function setDriver(?User $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setTrip($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getTrip() === $this) {
                $booking->setTrip(null);
            }
        }

        return $this;
    }

    // ==== Getters & Setters : Attributs ====
    public function getDepartureCity(): ?string
    {
        return $this->departureCity;
    }

    public function setDepartureCity(string $departureCity): static
    {
        $this->departureCity = $departureCity;
        return $this;
    }

    public function getDepartureAddress(): ?string
    {
        return $this->departureAddress;
    }

    public function setDepartureAddress(?string $departureAddress): static
    {
        $this->departureAddress = $departureAddress;
        return $this;
    }

    public function getDepartureCountry(): ?string
    {
        return $this->departureCountry;
    }

    public function setDepartureCountry(?string $departureCountry): static
    {
        $this->departureCountry = $departureCountry;
        return $this;
    }

    public function getDestinationCity(): ?string
    {
        return $this->destinationCity;
    }

    public function setDestinationCity(string $destinationCity): static
    {
        $this->destinationCity = $destinationCity;
        return $this;
    }

    public function getDestinationAddress(): ?string
    {
        return $this->destinationAddress;
    }

    public function setDestinationAddress(?string $destinationAddress): static
    {
        $this->destinationAddress = $destinationAddress;
        return $this;
    }

    public function getDestinationCountry(): ?string
    {
        return $this->destinationCountry;
    }

    public function setDestinationCountry(string $destinationCountry): static
    {
        $this->destinationCountry = $destinationCountry;
        return $this;
    }

    public function getDepartureAt(): ?\DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function setDepartureAt(\DateTimeImmutable $departureAt): static
    {
        $this->departureAt = $departureAt;
        return $this;
    }

    public function getReturnAt(): ?\DateTimeImmutable
    {
        return $this->returnAt;
    }

    public function setReturnAt(\DateTimeImmutable $returnAt): static
    {
        $this->returnAt = $returnAt;
        return $this;
    }

    public function getAvailableSeats(): ?int
    {
        return $this->availableSeats;
    }

    public function setAvailableSeats(int $availableSeats): static
    {
        $this->availableSeats = $availableSeats;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

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
}
