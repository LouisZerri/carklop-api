<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\SavingsEstimate;
use App\Entity\Trip;
use App\Tests\ApiTestCase;

class SavingsTest extends ApiTestCase
{
    private function createSavingsEstimate(): void
    {
        $savings = new SavingsEstimate();
        $savings->setCountryCode('DE');
        $savings->setCountryName('Allemagne');
        $savings->setAlimentaire(15);
        $savings->setAlcool(20);
        $savings->setCarburant(5);
        $savings->setTabac(10);
        $savings->setDescription('Alimentaire et bières moins chers');
        $this->em->persist($savings);
        $this->em->flush();
    }

    public function testEstimateParPays(): void
    {
        $this->createSavingsEstimate();

        $this->client->request('GET', '/api/savings/estimate?country=DE&budget=200');

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('DE', $response['country']);
        $this->assertEquals('Allemagne', $response['countryName']);
        $this->assertEquals(200, $response['budget']);
        $this->assertArrayHasKey('estimatedSavings', $response);
        $this->assertGreaterThan(0, $response['estimatedSavings']);
        $this->assertArrayHasKey('breakdown', $response);
        $this->assertArrayHasKey('alimentaire', $response['breakdown']);
    }

    public function testEstimateSansBudget(): void
    {
        $this->createSavingsEstimate();

        $this->client->request('GET', '/api/savings/estimate?country=DE');

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('DE', $response['country']);
        $this->assertNull($response['estimatedSavings']);
    }

    public function testEstimatePaysInexistant(): void
    {
        $this->client->request('GET', '/api/savings/estimate?country=XX&budget=100');

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('XX', $response['country']);
        $this->assertNull($response['estimatedSavings']);
        $this->assertEquals('Données non disponibles pour ce pays', $response['message']);
    }

    public function testEstimateSansPays(): void
    {
        $this->client->request('GET', '/api/savings/estimate');

        $this->assertResponseStatusCode(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('country requis', $response['error']);
    }

    public function testListePaysSupportes(): void
    {
        $this->createSavingsEstimate();

        $this->client->request('GET', '/api/savings/countries');

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertGreaterThan(0, count($response));
        $this->assertEquals('DE', $response[0]['code']);
        $this->assertEquals('Allemagne', $response[0]['name']);
    }

    public function testReservationAvecBudgetCalculeEconomies(): void
    {
        $this->createSavingsEstimate();

        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Créer un trajet vers l'Allemagne
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('+3 days 09:00'));
        $trip->setReturnAt(new \DateTimeImmutable('+3 days 14:00'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(500);
        $trip->setStatus('published');
        $trip->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip);
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        // Réserver avec un budget estimé
        $response = $this->apiRequest('POST', '/api/bookings/create', [
            'trip_id' => $trip->getId(),
            'seats' => 1,
            'estimated_budget' => 200,
        ]);

        $this->assertResponseStatusCode(200);
        $bookingId = $response['booking_id'];

        // Vérifier que les économies ont été calculées
        $booking = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals(200, $booking->getEstimatedBudget());
        $this->assertNotNull($booking->getEstimatedSavings());
        $this->assertGreaterThan(0, $booking->getEstimatedSavings());
    }

    public function testReservationSansBudget(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('+3 days 09:00'));
        $trip->setReturnAt(new \DateTimeImmutable('+3 days 14:00'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(500);
        $trip->setStatus('published');
        $trip->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip);
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/create', [
            'trip_id' => $trip->getId(),
            'seats' => 1,
        ]);

        $this->assertResponseStatusCode(200);
        $bookingId = $response['booking_id'];

        $booking = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertNull($booking->getEstimatedBudget());
        $this->assertNull($booking->getEstimatedSavings());
    }

    public function testMeStatsVide(): void
    {
        $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/me/stats');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(0, $response['totalSavings']);
        $this->assertEquals(0, $response['tripsAsPassenger']);
        $this->assertEquals(0, $response['tripsAsDriver']);
        $this->assertEquals('Aucune économie pour le moment', $response['message']);
    }

    public function testMeStatsAvecEconomies(): void
    {
        $this->createSavingsEstimate();

        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Créer un trajet complété
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('-1 week'));
        $trip->setReturnAt(new \DateTimeImmutable('-1 week +5 hours'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(500);
        $trip->setStatus('completed');
        $trip->setCreatedAt(new \DateTimeImmutable('-2 weeks'));
        $this->em->persist($trip);

        // Créer une réservation complétée avec économies
        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(500);
        $booking->setCommissionAmount(75);
        $booking->setTotalAmount(575);
        $booking->setStatus('completed');
        $booking->setEstimatedBudget(200);
        $booking->setEstimatedSavings(25);
        $booking->setCreatedAt(new \DateTimeImmutable('-2 weeks'));
        $this->em->persist($booking);
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/me/stats');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(25, $response['totalSavings']);
        $this->assertEquals(1, $response['tripsAsPassenger']);
        $this->assertEquals('25€ économisés sur 1 trajets', $response['message']);
    }

    public function testMeStatsMultiplesTrajets(): void
    {
        $this->createSavingsEstimate();

        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Créer 2 trajets complétés
        for ($i = 1; $i <= 2; $i++) {
            $trip = new Trip();
            $trip->setDriver($driver);
            $trip->setDepartureCity('Strasbourg');
            $trip->setDepartureCountry('FR');
            $trip->setDestinationCity('Kehl');
            $trip->setDestinationCountry('DE');
            $trip->setDepartureAt(new \DateTimeImmutable("-{$i} week"));
            $trip->setReturnAt(new \DateTimeImmutable("-{$i} week +5 hours"));
            $trip->setAvailableSeats(3);
            $trip->setPricePerSeat(500);
            $trip->setStatus('completed');
            $trip->setCreatedAt(new \DateTimeImmutable("-{$i} weeks -1 day"));
            $this->em->persist($trip);

            $booking = new Booking();
            $booking->setTrip($trip);
            $booking->setPassenger($passenger);
            $booking->setSeatsBooked(1);
            $booking->setPricePerSeat(500);
            $booking->setCommissionAmount(75);
            $booking->setTotalAmount(575);
            $booking->setStatus('completed');
            $booking->setEstimatedBudget(150);
            $booking->setEstimatedSavings(20);
            $booking->setCreatedAt(new \DateTimeImmutable("-{$i} weeks"));
            $this->em->persist($booking);
        }
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/me/stats');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(40, $response['totalSavings']); // 20 + 20
        $this->assertEquals(2, $response['tripsAsPassenger']);
        $this->assertEquals('40€ économisés sur 2 trajets', $response['message']);
    }

    public function testMeStatsNonAuthentifie(): void
    {
        $response = $this->apiRequest('GET', '/api/me/stats', null, false);

        $this->assertResponseStatusCode(401);
    }
}