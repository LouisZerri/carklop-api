<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Tests\ApiTestCase;

class CancellationTest extends ApiTestCase
{
    private function createTripWithDeparture($driver, \DateTimeImmutable $departureAt): Trip
    {
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt($departureAt);
        $trip->setReturnAt($departureAt->modify('+5 hours'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(1000);
        $trip->setStatus('published');
        $trip->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip);
        $this->em->flush();

        return $trip;
    }

    private function createBooking($trip, $passenger): Booking
    {
        // Réduire les places (simule une réservation confirmée)
        $trip->setAvailableSeats($trip->getAvailableSeats() - 1);
        
        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('paid');
        $booking->setStripePaymentIntentId('pi_test_123');
        $booking->setPaidAt(new \DateTimeImmutable());
        $booking->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }

    public function testAnnulationPassagerPlus48h(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+3 days'));
        $booking = $this->createBooking($trip, $passenger);
        
        $bookingId = $booking->getId();
        $tripId = $trip->getId();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/' . $bookingId . '/cancel');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Réservation annulée', $response['message']);
        $this->assertEquals(1150, $response['refunded_amount']);
        $this->assertEquals(0, $response['driver_receives']);

        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('cancelled', $bookingFresh->getStatus());
        $this->assertEquals('passenger', $bookingFresh->getCancelledBy());
        $this->assertEquals(1150, $bookingFresh->getRefundedAmount());

        $tripFresh = $this->em->getRepository(Trip::class)->find($tripId);
        $this->assertEquals(3, $tripFresh->getAvailableSeats());
    }

    public function testAnnulationPassagerEntre24hEt48h(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+36 hours'));
        $booking = $this->createBooking($trip, $passenger);
        
        $bookingId = $booking->getId();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/' . $bookingId . '/cancel');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(575, $response['refunded_amount']); // 50%
        $this->assertEquals(500, $response['driver_receives']); // 50%
    }

    public function testAnnulationPassagerMoins24h(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+12 hours'));
        $booking = $this->createBooking($trip, $passenger);
        
        $bookingId = $booking->getId();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/' . $bookingId . '/cancel');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(0, $response['refunded_amount']); // 0%
        $this->assertEquals(1000, $response['driver_receives']); // 100%
    }

    public function testAnnulationPassagerReservationNonPayee(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+3 days'));
        
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
        
        $bookingId = $booking->getId();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/' . $bookingId . '/cancel');

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Réservation non annulable', $response['error']);
    }

    public function testAnnulationPassagerAutreUtilisateur(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $otherUser = $this->createUser('other@test.fr', 'password123', 'Other', 'User', true);

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+3 days'));
        $booking = $this->createBooking($trip, $passenger);
        
        $bookingId = $booking->getId();

        $this->login('other@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/' . $bookingId . '/cancel');

        $this->assertResponseStatusCode(403);
    }

    public function testAnnulationParConducteur(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger1 = $this->createUser('passenger1@test.fr', 'password123', 'Jean', 'Passenger1', true);
        $passenger2 = $this->createUser('passenger2@test.fr', 'password123', 'Claire', 'Passenger2', true);

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+3 days'));
        $booking1 = $this->createBooking($trip, $passenger1);
        $booking2 = $this->createBooking($trip, $passenger2);
        
        $tripId = $trip->getId();
        $booking1Id = $booking1->getId();
        $booking2Id = $booking2->getId();

        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/trip/' . $tripId . '/cancel');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Trajet annulé', $response['message']);
        $this->assertEquals(2, $response['bookings_refunded']);

        $tripFresh = $this->em->getRepository(Trip::class)->find($tripId);
        $this->assertEquals('cancelled', $tripFresh->getStatus());

        $booking1Fresh = $this->em->getRepository(Booking::class)->find($booking1Id);
        $this->assertEquals('refunded', $booking1Fresh->getStatus());
        $this->assertEquals('driver', $booking1Fresh->getCancelledBy());
        $this->assertEquals(1150, $booking1Fresh->getRefundedAmount());

        $booking2Fresh = $this->em->getRepository(Booking::class)->find($booking2Id);
        $this->assertEquals('refunded', $booking2Fresh->getStatus());
    }

    public function testAnnulationParConducteurNonAutorise(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $otherDriver = $this->createUser('other@test.fr', 'password123', 'Other', 'Driver', true, 'acct_other');

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+3 days'));
        $tripId = $trip->getId();

        $this->login('other@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/trip/' . $tripId . '/cancel');

        $this->assertResponseStatusCode(403);
    }

    public function testAnnulationTrajetDejaAnnule(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');

        $trip = $this->createTripWithDeparture($driver, new \DateTimeImmutable('+3 days'));
        $trip->setStatus('cancelled');
        $this->em->flush();
        
        $tripId = $trip->getId();

        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/bookings/trip/' . $tripId . '/cancel');

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Trajet déjà annulé', $response['error']);
    }
}