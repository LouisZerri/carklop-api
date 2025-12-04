<?php

namespace App\DataFixtures;

use App\Entity\SavingsEstimate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SavingsEstimateFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $countries = [
            [
                'code' => 'DE',
                'name' => 'Allemagne',
                'alimentaire' => 15,
                'alcool' => 20,
                'carburant' => 5,
                'tabac' => 10,
                'description' => 'Alimentaire et bières moins chers',
            ],
            [
                'code' => 'LU',
                'name' => 'Luxembourg',
                'alimentaire' => 10,
                'alcool' => 25,
                'carburant' => 25,
                'tabac' => 40,
                'description' => 'Carburant et tabac très avantageux',
            ],
            [
                'code' => 'BE',
                'name' => 'Belgique',
                'alimentaire' => 5,
                'alcool' => 15,
                'carburant' => 10,
                'tabac' => 20,
                'description' => 'Chocolat et bières à prix réduit',
            ],
            [
                'code' => 'ES',
                'name' => 'Espagne',
                'alimentaire' => 20,
                'alcool' => 30,
                'carburant' => 15,
                'tabac' => 35,
                'description' => 'Alimentation et tabac économiques',
            ],
            [
                'code' => 'IT',
                'name' => 'Italie',
                'alimentaire' => 10,
                'alcool' => 20,
                'carburant' => 10,
                'tabac' => 15,
                'description' => 'Produits alimentaires avantageux',
            ],
            [
                'code' => 'CH',
                'name' => 'Suisse',
                'alimentaire' => -30,
                'alcool' => -40,
                'carburant' => -20,
                'tabac' => -25,
                'description' => '⚠️ Plus cher - idéal pour travailleurs frontaliers',
            ],
            [
                'code' => 'AD',
                'name' => 'Andorre',
                'alimentaire' => 15,
                'alcool' => 40,
                'carburant' => 20,
                'tabac' => 50,
                'description' => 'Tabac et alcool très avantageux (duty-free)',
            ],
        ];

        foreach ($countries as $data) {
            $savings = new SavingsEstimate();
            $savings->setCountryCode($data['code']);
            $savings->setCountryName($data['name']);
            $savings->setAlimentaire($data['alimentaire']);
            $savings->setAlcool($data['alcool']);
            $savings->setCarburant($data['carburant']);
            $savings->setTabac($data['tabac']);
            $savings->setDescription($data['description']);
            $manager->persist($savings);
        }

        $manager->flush();
    }
}