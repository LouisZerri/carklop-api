<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use App\State\UserPasswordHasher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: UserPasswordHasher::class),
        new Patch(
            processor: UserPasswordHasher::class,
            security: "is_granted('ROLE_ADMIN') or object == user",
            securityMessage: "Vous ne pouvez modifier que votre propre profil."
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // ----- Identité et authentification -----
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user:read', 'user:write', 'trip:read', 'booking:read'])]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'Email invalide')]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[Groups(['user:write'])]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire', groups: ['user:create'])]
    #[Assert\Length(min: 6, minMessage: 'Minimum 6 caractères')]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $appleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetPasswordToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetPasswordTokenExpiresAt = null;

    // ----- Infos utilisateur -----
    #[ORM\Column(length: 50)]
    #[Groups(['user:read', 'user:write', 'trip:read', 'booking:read'])]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    private ?string $firstName = null;

    #[ORM\Column(length: 50)]
    #[Groups(['user:read', 'user:write', 'trip:read', 'booking:read'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\Regex(pattern: '/^[\d\s\+\-\.]+$/', message: 'Numéro invalide')]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write', 'trip:read', 'booking:read'])]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $bio = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    #[SerializedName('isVerified')]
    private ?bool $isVerified = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeAccountId = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // ----- Relations principales -----
    /**
     * @var Collection<int, Trip>
     */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'driver', orphanRemoval: true)]
    private Collection $trips;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'passenger', orphanRemoval: true)]
    private Collection $bookings;

    // ----- Relations secondaires -----
    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $reviewsGiven;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'target', orphanRemoval: true)]
    private Collection $reviewsReceived;

    /**
     * @var Collection<int, DeviceToken>
     */
    #[ORM\OneToMany(targetEntity: DeviceToken::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $deviceTokens;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $notifications;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'driver')]
    private Collection $conversationsAsDriver;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'passenger')]
    private Collection $conversationsAsPassenger;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $messagesSent;

    // ----- Constructeur -----
    public function __construct()
    {
        $this->trips = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->isVerified = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->reviewsGiven = new ArrayCollection();
        $this->reviewsReceived = new ArrayCollection();
        $this->deviceTokens = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->conversationsAsDriver = new ArrayCollection();
        $this->conversationsAsPassenger = new ArrayCollection();
        $this->messagesSent = new ArrayCollection();
    }

    // ----- Getters/Setters Id & Auth -----
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): static
    {
        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getAppleId(): ?string
    {
        return $this->appleId;
    }

    public function setAppleId(?string $appleId): static
    {
        $this->appleId = $appleId;
        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;
        return $this;
    }

    public function getResetPasswordTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetPasswordTokenExpiresAt;
    }

    public function setResetPasswordTokenExpiresAt(?\DateTimeImmutable $resetPasswordTokenExpiresAt): static
    {
        $this->resetPasswordTokenExpiresAt = $resetPasswordTokenExpiresAt;
        return $this;
    }

    // ----- Getters/Setters infos utilisateur -----
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    #[Groups(['user:read', 'trip:read', 'booking:read'])]
    public function getDefaultAvatar(): string
    {
        $name = urlencode($this->firstName . '+' . $this->lastName);
        return "https://ui-avatars.com/api/?name={$name}&background=random&color=fff&size=128";
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    // Getter pour que /api/me retourne bien isVerified
    #[Groups(['user:read'])]
    #[SerializedName('isVerified')]
    public function getIsVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getStripeAccountId(): ?string
    {
        return $this->stripeAccountId;
    }

    public function setStripeAccountId(?string $stripeAccountId): static
    {
        $this->stripeAccountId = $stripeAccountId;
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

    // ----- Relations principales : trip et booking -----
    /**
     * @return Collection<int, Trip>
     */
    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): static
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setDriver($this);
        }
        return $this;
    }

    public function removeTrip(Trip $trip): static
    {
        if ($this->trips->removeElement($trip)) {
            if ($trip->getDriver() === $this) {
                $trip->setDriver(null);
            }
        }
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
            $booking->setPassenger($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getPassenger() === $this) {
                $booking->setPassenger(null);
            }
        }
        return $this;
    }

    // ----- Reviews (reçues & données) -----
    /**
     * @return Collection<int, Review>
     */
    public function getReviewsGiven(): Collection
    {
        return $this->reviewsGiven;
    }

    public function addReviewsGiven(Review $reviewsGiven): static
    {
        if (!$this->reviewsGiven->contains($reviewsGiven)) {
            $this->reviewsGiven->add($reviewsGiven);
            $reviewsGiven->setAuthor($this);
        }
        return $this;
    }

    public function removeReviewsGiven(Review $reviewsGiven): static
    {
        if ($this->reviewsGiven->removeElement($reviewsGiven)) {
            if ($reviewsGiven->getAuthor() === $this) {
                $reviewsGiven->setAuthor(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviewsReceived(): Collection
    {
        return $this->reviewsReceived;
    }

    public function addReviewsReceived(Review $reviewsReceived): static
    {
        if (!$this->reviewsReceived->contains($reviewsReceived)) {
            $this->reviewsReceived->add($reviewsReceived);
            $reviewsReceived->setTarget($this);
        }
        return $this;
    }

    public function removeReviewsReceived(Review $reviewsReceived): static
    {
        if ($this->reviewsReceived->removeElement($reviewsReceived)) {
            if ($reviewsReceived->getTarget() === $this) {
                $reviewsReceived->setTarget(null);
            }
        }
        return $this;
    }

    #[Groups(['user:read', 'trip:read', 'booking:read'])]
    public function getAverageRating(): ?float
    {
        $reviews = $this->reviewsReceived;
        if ($reviews->isEmpty()) {
            return null;
        }
        $total = 0;
        foreach ($reviews as $review) {
            $total += $review->getRating();
        }
        return round($total / $reviews->count(), 1);
    }

    #[Groups(['user:read', 'trip:read', 'booking:read'])]
    public function getReviewsCount(): int
    {
        return $this->reviewsReceived->count();
    }

    // ----- Tokens, notifications, conversations & messages -----
    /**
     * @return Collection<int, DeviceToken>
     */
    public function getDeviceTokens(): Collection
    {
        return $this->deviceTokens;
    }

    public function addDeviceToken(DeviceToken $deviceToken): static
    {
        if (!$this->deviceTokens->contains($deviceToken)) {
            $this->deviceTokens->add($deviceToken);
            $deviceToken->setUser($this);
        }
        return $this;
    }

    public function removeDeviceToken(DeviceToken $deviceToken): static
    {
        if ($this->deviceTokens->removeElement($deviceToken)) {
            if ($deviceToken->getUser() === $this) {
                $deviceToken->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }
        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationsAsDriver(): Collection
    {
        return $this->conversationsAsDriver;
    }

    public function addConversationsAsDriver(Conversation $conversationsAsDriver): static
    {
        if (!$this->conversationsAsDriver->contains($conversationsAsDriver)) {
            $this->conversationsAsDriver->add($conversationsAsDriver);
            $conversationsAsDriver->setDriver($this);
        }
        return $this;
    }

    public function removeConversationsAsDriver(Conversation $conversationsAsDriver): static
    {
        if ($this->conversationsAsDriver->removeElement($conversationsAsDriver)) {
            if ($conversationsAsDriver->getDriver() === $this) {
                $conversationsAsDriver->setDriver(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationsAsPassenger(): Collection
    {
        return $this->conversationsAsPassenger;
    }

    public function addConversationsAsPassenger(Conversation $conversationsAsPassenger): static
    {
        if (!$this->conversationsAsPassenger->contains($conversationsAsPassenger)) {
            $this->conversationsAsPassenger->add($conversationsAsPassenger);
            $conversationsAsPassenger->setPassenger($this);
        }
        return $this;
    }

    public function removeConversationsAsPassenger(Conversation $conversationsAsPassenger): static
    {
        if ($this->conversationsAsPassenger->removeElement($conversationsAsPassenger)) {
            if ($conversationsAsPassenger->getPassenger() === $this) {
                $conversationsAsPassenger->setPassenger(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessagesSent(): Collection
    {
        return $this->messagesSent;
    }

    public function addMessagesSent(Message $messagesSent): static
    {
        if (!$this->messagesSent->contains($messagesSent)) {
            $this->messagesSent->add($messagesSent);
            $messagesSent->setSender($this);
        }
        return $this;
    }

    public function removeMessagesSent(Message $messagesSent): static
    {
        if ($this->messagesSent->removeElement($messagesSent)) {
            if ($messagesSent->getSender() === $this) {
                $messagesSent->setSender(null);
            }
        }
        return $this;
    }

}
