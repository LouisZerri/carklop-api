<?php

namespace App\Controller;

use App\Entity\SavingsEstimate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/savings')]
class SavingsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/estimate', name: 'savings_estimate', methods: ['GET'])]
    public function estimate(Request $request): JsonResponse
    {
        $country = strtoupper($request->query->get('country', ''));
        $budget = (int) $request->query->get('budget', 0);

        if (!$country) {
            return new JsonResponse(['error' => 'country requis'], 400);
        }

        $savings = $this->em->getRepository(SavingsEstimate::class)
            ->findOneBy(['countryCode' => $country]);

        if (!$savings) {
            return new JsonResponse([
                'country' => $country,
                'budget' => $budget,
                'estimatedSavings' => null,
                'message' => 'Données non disponibles pour ce pays',
            ]);
        }

        // Calcul de l'économie estimée (moyenne des pourcentages)
        $avgPercent = ($savings->getAlimentaire() + $savings->getAlcool() + $savings->getCarburant() + $savings->getTabac()) / 4;
        $estimatedSavings = $budget > 0 ? (int) round($budget * abs($avgPercent) / 100) : null;

        // Breakdown par catégorie
        $breakdown = null;
        if ($budget > 0) {
            // Répartition estimée du budget : 50% alimentaire, 20% alcool, 20% carburant, 10% tabac
            $breakdown = [
                'alimentaire' => (int) round($budget * 0.50 * abs($savings->getAlimentaire()) / 100),
                'alcool' => (int) round($budget * 0.20 * abs($savings->getAlcool()) / 100),
                'carburant' => (int) round($budget * 0.20 * abs($savings->getCarburant()) / 100),
                'tabac' => (int) round($budget * 0.10 * abs($savings->getTabac()) / 100),
            ];
        }

        return new JsonResponse([
            'country' => $country,
            'countryName' => $savings->getCountryName(),
            'budget' => $budget,
            'estimatedSavings' => $estimatedSavings,
            'breakdown' => $breakdown,
            'percentages' => [
                'alimentaire' => $savings->getAlimentaire(),
                'alcool' => $savings->getAlcool(),
                'carburant' => $savings->getCarburant(),
                'tabac' => $savings->getTabac(),
            ],
            'description' => $savings->getDescription(),
            'message' => $estimatedSavings 
                ? "Économie estimée : ~{$estimatedSavings}€ sur un budget de {$budget}€"
                : $savings->getDescription(),
        ]);
    }

    #[Route('/countries', name: 'savings_countries', methods: ['GET'])]
    public function countries(): JsonResponse
    {
        $countries = $this->em->getRepository(SavingsEstimate::class)->findAll();

        $data = [];
        foreach ($countries as $country) {
            $data[] = [
                'code' => $country->getCountryCode(),
                'name' => $country->getCountryName(),
                'description' => $country->getDescription(),
                'percentages' => [
                    'alimentaire' => $country->getAlimentaire(),
                    'alcool' => $country->getAlcool(),
                    'carburant' => $country->getCarburant(),
                    'tabac' => $country->getTabac(),
                ],
            ];
        }

        return new JsonResponse($data);
    }
}