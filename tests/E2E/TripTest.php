<?php

namespace App\Tests\E2E;

use App\Entity\Trip;
use App\Tests\ApiTestCase;

class TripTest extends ApiTestCase
{
    public function testListeTrajetsPublic(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('+2 days 09:00'));
        $trip->setReturnAt(new \DateTimeImmutable('+2 days 14:00'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(800);
        $trip->setStatus('published');
        $trip->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip);
        $this->em->flush();

        $response = $this->apiRequest('GET', '/api/trips', null, false);

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('member', $response);
        $this->assertCount(1, $response['member']);
        $this->assertEquals('Strasbourg', $response['member'][0]['departureCity']);
    }

    public function testCreerTrajetUtilisateurVerifie(): void
    {
        $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/trips', [
            'departureCity' => 'Metz',
            'departureCountry' => 'FR',
            'destinationCity' => 'Luxembourg',
            'destinationCountry' => 'LU',
            'departureAt' => (new \DateTimeImmutable('+3 days 08:00'))->format('c'),
            'returnAt' => (new \DateTimeImmutable('+3 days 15:00'))->format('c'),
            'availableSeats' => 2,
            'pricePerSeat' => 1500,
            'status' => 'published',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertEquals('Metz', $response['departureCity']);
        $this->assertEquals('Luxembourg', $response['destinationCity']);
        $this->assertEquals(2, $response['availableSeats']);
    }

    public function testCreerTrajetUtilisateurNonVerifie(): void
    {
        $this->createUser('nonverifie@test.fr', 'password123', 'Test', 'User', false);
        $this->login('nonverifie@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/trips', [
            'departureCity' => 'Metz',
            'departureCountry' => 'FR',
            'destinationCity' => 'Luxembourg',
            'destinationCountry' => 'LU',
            'departureAt' => (new \DateTimeImmutable('+3 days 08:00'))->format('c'),
            'returnAt' => (new \DateTimeImmutable('+3 days 15:00'))->format('c'),
            'availableSeats' => 2,
            'pricePerSeat' => 1500,
            'status' => 'published',
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testCreerTrajetNonAuthentifie(): void
    {
        $response = $this->apiRequest('POST', '/api/trips', [
            'departureCity' => 'Metz',
            'departureCountry' => 'FR',
            'destinationCity' => 'Luxembourg',
            'destinationCountry' => 'LU',
            'departureAt' => (new \DateTimeImmutable('+3 days 08:00'))->format('c'),
            'returnAt' => (new \DateTimeImmutable('+3 days 15:00'))->format('c'),
            'availableSeats' => 2,
            'pricePerSeat' => 1500,
            'status' => 'published',
        ], false);

        // 401 (JWT) ou 403 (AccessDenied) sont acceptables
        $this->assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testCreerTrajetRetourAvantDepart(): void
    {
        $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/trips', [
            'departureCity' => 'Metz',
            'departureCountry' => 'FR',
            'destinationCity' => 'Luxembourg',
            'destinationCountry' => 'LU',
            'departureAt' => (new \DateTimeImmutable('+3 days 15:00'))->format('c'),
            'returnAt' => (new \DateTimeImmutable('+3 days 08:00'))->format('c'),
            'availableSeats' => 2,
            'pricePerSeat' => 1500,
            'status' => 'published',
        ]);

        $this->assertResponseStatusCode(422);
    }

    public function testMesTrajets(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('+2 days 09:00'));
        $trip->setReturnAt(new \DateTimeImmutable('+2 days 14:00'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(800);
        $trip->setStatus('published');
        $trip->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip);
        $this->em->flush();

        $this->login('driver@test.fr', 'password123');
        $response = $this->apiRequest('GET', '/api/me/trips');

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $response);
        $this->assertEquals('Strasbourg', $response[0]['departureCity']);
    }

    public function testFiltrerTrajetsParPays(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        
        $trip1 = new Trip();
        $trip1->setDriver($driver);
        $trip1->setDepartureCity('Strasbourg');
        $trip1->setDepartureCountry('FR');
        $trip1->setDestinationCity('Kehl');
        $trip1->setDestinationCountry('DE');
        $trip1->setDepartureAt(new \DateTimeImmutable('+2 days 09:00'));
        $trip1->setReturnAt(new \DateTimeImmutable('+2 days 14:00'));
        $trip1->setAvailableSeats(3);
        $trip1->setPricePerSeat(800);
        $trip1->setStatus('published');
        $trip1->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip1);

        $trip2 = new Trip();
        $trip2->setDriver($driver);
        $trip2->setDepartureCity('Nice');
        $trip2->setDepartureCountry('FR');
        $trip2->setDestinationCity('Vintimille');
        $trip2->setDestinationCountry('IT');
        $trip2->setDepartureAt(new \DateTimeImmutable('+3 days 09:00'));
        $trip2->setReturnAt(new \DateTimeImmutable('+3 days 14:00'));
        $trip2->setAvailableSeats(4);
        $trip2->setPricePerSeat(1200);
        $trip2->setStatus('published');
        $trip2->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip2);

        $this->em->flush();

        $response = $this->apiRequest('GET', '/api/trips?destinationCountry=DE', null, false);

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $response['member']);
        $this->assertEquals('Kehl', $response['member'][0]['destinationCity']);
    }
}