<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Tests\ApiTestCase;

class BookingTest extends ApiTestCase
{
    private function createTrip($driver, string $status = 'published', int $seats = 3): Trip
    {
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('+5 days 09:00'));
        $trip->setReturnAt(new \DateTimeImmutable('+5 days 14:00'));
        $trip->setAvailableSeats($seats);
        $trip->setPricePerSeat(1000);
        $trip->setStatus($status);
        $trip->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip);
        $this->em->flush();

        return $trip;
    }

    public function testCreerReservation(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $trip = $this->createTrip($driver);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/create', [
            'trip_id' => $trip->getId(),
            'seats' => 2,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('booking_id', $response);
        $this->assertArrayHasKey('client_secret', $response);
        $this->assertEquals(2300, $response['amount']); // 2000 + 300 (15%)
        $this->assertEquals(300, $response['commission']);
        $this->assertEquals(2000, $response['subtotal']);
    }

    public function testCreerReservationUtilisateurNonVerifie(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', false);
        $trip = $this->createTrip($driver);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/create', [
            'trip_id' => $trip->getId(),
            'seats' => 1,
        ]);

        $this->assertResponseStatusCode(403);
        $this->assertEquals('Vous devez vérifier votre email avant de réserver.', $response['error']);
    }

    public function testCreerReservationPropreTrajet(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $trip = $this->createTrip($driver);

        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/create', [
            'trip_id' => $trip->getId(),
            'seats' => 1,
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Vous ne pouvez pas réserver votre propre trajet', $response['error']);
    }

    public function testCreerReservationTrajetNonPublie(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $trip = $this->createTrip($driver, 'draft');

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/create', [
            'trip_id' => $trip->getId(),
            'seats' => 1,
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Trajet non disponible', $response['error']);
    }

    public function testCreerReservationTropDePlaces(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $trip = $this->createTrip($driver, 'published', 2);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/create', [
            'trip_id' => $trip->getId(),
            'seats' => 5,
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Nombre de places invalide', $response['error']);
    }

    public function testConfirmerReservation(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $trip = $this->createTrip($driver);
        $tripId = $trip->getId();

        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('pending');
        $booking->setStripePaymentIntentId('pi_test_123');
        $booking->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);
        $this->em->flush();

        $bookingId = $booking->getId();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/' . $bookingId . '/confirm');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Réservation confirmée', $response['message']);
        $this->assertEquals('paid', $response['status']);
        $this->assertArrayHasKey('conversation_id', $response);

        // Recharger depuis la base
        $tripFresh = $this->em->getRepository(\App\Entity\Trip::class)->find($tripId);
        $this->assertEquals(2, $tripFresh->getAvailableSeats());
    }

    public function testConfirmerReservationAutreUtilisateur(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $otherUser = $this->createUser('other@test.fr', 'password123', 'Other', 'User', true);
        $trip = $this->createTrip($driver);

        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('pending');
        $booking->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);
        $this->em->flush();

        $this->login('other@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/' . $booking->getId() . '/confirm');

        $this->assertResponseStatusCode(403);
    }

    public function testMesReservations(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $trip = $this->createTrip($driver);

        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('paid');
        $booking->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/me/bookings');

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $response);
        $this->assertEquals(1150, $response[0]['totalAmount']);
    }
}
