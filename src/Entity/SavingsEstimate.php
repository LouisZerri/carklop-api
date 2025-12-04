<?php

namespace App\Entity;

use App\Repository\SavingsEstimateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SavingsEstimateRepository::class)]
class SavingsEstimate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 100)]
    private ?string $countryName = null;

    #[ORM\Column]
    private ?int $alimentaire = null;

    #[ORM\Column]
    private ?int $alcool = null;

    #[ORM\Column]
    private ?int $carburant = null;

    #[ORM\Column]
    private ?int $tabac = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): static
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function setCountryName(string $countryName): static
    {
        $this->countryName = $countryName;

        return $this;
    }

    public function getAlimentaire(): ?int
    {
        return $this->alimentaire;
    }

    public function setAlimentaire(int $alimentaire): static
    {
        $this->alimentaire = $alimentaire;

        return $this;
    }

    public function getAlcool(): ?int
    {
        return $this->alcool;
    }

    public function setAlcool(int $alcool): static
    {
        $this->alcool = $alcool;

        return $this;
    }

    public function getCarburant(): ?int
    {
        return $this->carburant;
    }

    public function setCarburant(int $carburant): static
    {
        $this->carburant = $carburant;

        return $this;
    }

    public function getTabac(): ?int
    {
        return $this->tabac;
    }

    public function setTabac(int $tabac): static
    {
        $this->tabac = $tabac;

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
}
