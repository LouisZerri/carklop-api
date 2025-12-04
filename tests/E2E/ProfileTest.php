<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\Trip;
use App\Tests\ApiTestCase;

class ProfileTest extends ApiTestCase
{
    public function testVoirProfilUtilisateur(): void
    {
        $user = $this->createUser('marie@test.fr', 'password123', 'Marie', 'Laurent', true);
        $user->setBio('Conductrice depuis 3 ans, je fais régulièrement Strasbourg-Kehl.');
        $this->em->flush();

        $response = $this->apiRequest('GET', '/api/users/' . $user->getId() . '/profile', null, false);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Marie', $response['firstName']);
        $this->assertEquals('L.', $response['lastName']); // Initiale seulement
        $this->assertEquals('Conductrice depuis 3 ans, je fais régulièrement Strasbourg-Kehl.', $response['bio']);
        $this->assertArrayHasKey('averageRating', $response);
        $this->assertArrayHasKey('reviewsCount', $response);
        $this->assertArrayHasKey('tripsAsDriver', $response);
        $this->assertArrayHasKey('tripsAsPassenger', $response);
        $this->assertArrayHasKey('memberSince', $response);
        $this->assertArrayHasKey('recentReviews', $response);
    }

    public function testProfilUtilisateurInexistant(): void
    {
        $response = $this->apiRequest('GET', '/api/users/99999/profile', null, false);

        $this->assertResponseStatusCode(404);
        $this->assertEquals('Utilisateur introuvable', $response['error']);
    }

    public function testProfilAvecTrajetsEffectues(): void
    {
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
        $trip->setPricePerSeat(1000);
        $trip->setStatus('completed');
        $trip->setCreatedAt(new \DateTimeImmutable('-2 weeks'));
        $this->em->persist($trip);

        // Créer une réservation complétée
        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('completed');
        $booking->setCreatedAt(new \DateTimeImmutable('-2 weeks'));
        $this->em->persist($booking);
        $this->em->flush();

        // Vérifier le profil du conducteur
        $response = $this->apiRequest('GET', '/api/users/' . $driver->getId() . '/profile', null, false);

        $this->assertResponseStatusCode(200);
        $this->assertEquals(1, $response['tripsAsDriver']);

        // Vérifier le profil du passager
        $response = $this->apiRequest('GET', '/api/users/' . $passenger->getId() . '/profile', null, false);

        $this->assertResponseStatusCode(200);
        $this->assertEquals(1, $response['tripsAsPassenger']);
    }

    public function testProfilAvecAvis(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Créer un trajet et une réservation
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt(new \DateTimeImmutable('-1 week'));
        $trip->setReturnAt(new \DateTimeImmutable('-1 week +5 hours'));
        $trip->setAvailableSeats(3);
        $trip->setPricePerSeat(1000);
        $trip->setStatus('completed');
        $trip->setCreatedAt(new \DateTimeImmutable('-2 weeks'));
        $this->em->persist($trip);

        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('completed');
        $booking->setCreatedAt(new \DateTimeImmutable('-2 weeks'));
        $this->em->persist($booking);

        // Créer un avis
        $review = new Review();
        $review->setBooking($booking);
        $review->setAuthor($passenger);
        $review->setTarget($driver);
        $review->setRating(5);
        $review->setComment('Excellent trajet !');
        $review->setCreatedAt(new \DateTimeImmutable('-3 days'));
        $this->em->persist($review);
        $this->em->flush();

        $response = $this->apiRequest('GET', '/api/users/' . $driver->getId() . '/profile', null, false);

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $response['recentReviews']);
        $this->assertEquals(5, $response['recentReviews'][0]['rating']);
        $this->assertEquals('Excellent trajet !', $response['recentReviews'][0]['comment']);
        $this->assertEquals('Jean', $response['recentReviews'][0]['author']['firstName']);
    }

    public function testProfilSansBio(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);

        $response = $this->apiRequest('GET', '/api/users/' . $user->getId() . '/profile', null, false);

        $this->assertResponseStatusCode(200);
        $this->assertNull($response['bio']);
    }

    public function testModifierBio(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('PATCH', '/api/users/' . $user->getId(), [
            'bio' => 'Je suis passionné de covoiturage transfrontalier !',
        ]);

        $this->assertResponseStatusCode(200);

        // Vérifier via le profil
        $profileResponse = $this->apiRequest('GET', '/api/users/' . $user->getId() . '/profile', null, false);
        $this->assertEquals('Je suis passionné de covoiturage transfrontalier !', $profileResponse['bio']);
    }
}