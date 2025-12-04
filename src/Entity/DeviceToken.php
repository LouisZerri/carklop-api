<?php

namespace App\Entity;

use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceTokenRepository::class)]
class DeviceToken
{
    // ==== Identifiant principal ====
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ==== Relations ====
    #[ORM\ManyToOne(inversedBy: 'deviceTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // ==== Attributs ====
    #[ORM\Column(length: 255)]
    private ?string $token = null;

    #[ORM\Column(length: 20)]
    private ?string $platform = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // ==== Getters & Setters : Identifiant ====
    public function getId(): ?int
    {
        return $this->id;
    }

    // ==== Getters & Setters : Relations ====
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    // ==== Getters & Setters : Attributs ====
    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): static
    {
        $this->platform = $platform;
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
