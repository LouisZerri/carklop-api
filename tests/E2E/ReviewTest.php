<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\Trip;
use App\Tests\ApiTestCase;

class ReviewTest extends ApiTestCase
{
    private function createCompletedBooking($driver, $passenger): Booking
    {
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('-2 days 09:00'));
        $trip->setReturnAt(new \DateTimeImmutable('-2 days 14:00'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(1000);
        $trip->setStatus('completed');
        $trip->setCreatedAt(new \DateTimeImmutable('-5 days'));
        $this->em->persist($trip);

        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('completed');
        $booking->setStripePaymentIntentId('pi_test_123');
        $booking->setStripeTransferId('tr_test_123');
        $booking->setPaidAt(new \DateTimeImmutable('-3 days'));
        $booking->setCreatedAt(new \DateTimeImmutable('-3 days'));
        $this->em->persist($booking);

        $this->em->flush();

        return $booking;
    }

    public function testCreerAvis(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $booking = $this->createCompletedBooking($driver, $passenger);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/reviews', [
            'booking_id' => $booking->getId(),
            'rating' => 5,
            'comment' => 'Super trajet, conductrice très sympa !',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertArrayHasKey('review_id', $response);
        $this->assertEquals('Avis publié', $response['message']);
    }

    public function testCreerAvisSansCommentaire(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $booking = $this->createCompletedBooking($driver, $passenger);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/reviews', [
            'booking_id' => $booking->getId(),
            'rating' => 4,
        ]);

        $this->assertResponseStatusCode(201);
    }

    public function testCreerAvisNoteInvalide(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $booking = $this->createCompletedBooking($driver, $passenger);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/reviews', [
            'booking_id' => $booking->getId(),
            'rating' => 6,
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Note entre 1 et 5', $response['error']);
    }

    public function testCreerAvisTrajetNonTermine(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('+5 days 09:00'));
        $trip->setReturnAt(new \DateTimeImmutable('+5 days 14:00'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(1000);
        $trip->setStatus('published');
        $trip->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($trip);

        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('paid'); // Pas encore completed
        $booking->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/reviews', [
            'booking_id' => $booking->getId(),
            'rating' => 5,
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Le trajet doit être terminé pour laisser un avis', $response['error']);
    }

    public function testCreerAvisDejaExistant(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        $booking = $this->createCompletedBooking($driver, $passenger);

        // Créer un avis existant
        $review = new Review();
        $review->setBooking($booking);
        $review->setAuthor($passenger);
        $review->setTarget($driver);
        $review->setRating(5);
        $review->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($review);
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/reviews', [
            'booking_id' => $booking->getId(),
            'rating' => 4,
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Vous avez déjà laissé un avis', $response['error']);
    }

    public function testCreerAvisNonAutorise(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $otherUser = $this->createUser('other@test.fr', 'password123', 'Other', 'User', true);

        $booking = $this->createCompletedBooking($driver, $passenger);

        $this->login('other@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/reviews', [
            'booking_id' => $booking->getId(),
            'rating' => 5,
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testListerAvisUtilisateur(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger1 = $this->createUser('passenger1@test.fr', 'password123', 'Jean', 'Passenger1', true);
        $passenger2 = $this->createUser('passenger2@test.fr', 'password123', 'Claire', 'Passenger2', true);

        $booking1 = $this->createCompletedBooking($driver, $passenger1);
        
        $review1 = new Review();
        $review1->setBooking($booking1);
        $review1->setAuthor($passenger1);
        $review1->setTarget($driver);
        $review1->setRating(5);
        $review1->setComment('Excellent !');
        $review1->setCreatedAt(new \DateTimeImmutable('-1 day'));
        $this->em->persist($review1);

        $booking2 = $this->createCompletedBooking($driver, $passenger2);

        $review2 = new Review();
        $review2->setBooking($booking2);
        $review2->setAuthor($passenger2);
        $review2->setTarget($driver);
        $review2->setRating(4);
        $review2->setComment('Très bien');
        $review2->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($review2);

        $this->em->flush();

        $response = $this->apiRequest('GET', '/api/reviews/user/' . $driver->getId(), null, false);

        $this->assertResponseStatusCode(200);
        $this->assertCount(2, $response);
        $this->assertEquals(4, $response[0]['rating']); // Plus récent en premier
        $this->assertEquals(5, $response[1]['rating']);
    }

    public function testNoteMoyenneUtilisateur(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger1 = $this->createUser('passenger1@test.fr', 'password123', 'Jean', 'Passenger1', true);
        $passenger2 = $this->createUser('passenger2@test.fr', 'password123', 'Claire', 'Passenger2', true);

        $booking1 = $this->createCompletedBooking($driver, $passenger1);
        
        $review1 = new Review();
        $review1->setBooking($booking1);
        $review1->setAuthor($passenger1);
        $review1->setTarget($driver);
        $review1->setRating(5);
        $review1->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($review1);

        $booking2 = $this->createCompletedBooking($driver, $passenger2);

        $review2 = new Review();
        $review2->setBooking($booking2);
        $review2->setAuthor($passenger2);
        $review2->setTarget($driver);
        $review2->setRating(4);
        $review2->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($review2);

        $this->em->flush();

        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/me');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(4.5, $response['averageRating']); // (5+4)/2
        $this->assertEquals(2, $response['reviewsCount']);
    }
}