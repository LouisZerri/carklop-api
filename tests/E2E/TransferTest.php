<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\Trip;
use App\MessageHandler\ProcessTransfersHandler;
use App\Tests\ApiTestCase;

class TransferTest extends ApiTestCase
{
    private function createCompletedTrip($driver, $passenger, \DateTimeImmutable $returnAt): Booking
    {
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setDepartureCity('Strasbourg');
        $trip->setDepartureCountry('FR');
        $trip->setDestinationCity('Kehl');
        $trip->setDestinationCountry('DE');
        $trip->setDepartureAt($returnAt->modify('-5 hours'));
        $trip->setReturnAt($returnAt);
        $trip->setAvailableSeats(2);
        $trip->setPricePerSeat(1000);
        $trip->setStatus('published');
        $trip->setCreatedAt(new \DateTimeImmutable('-1 week'));
        $this->em->persist($trip);

        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($passenger);
        $booking->setSeatsBooked(1);
        $booking->setPricePerSeat(1000);
        $booking->setCommissionAmount(150);
        $booking->setTotalAmount(1150);
        $booking->setStatus('paid');
        $booking->setStripePaymentIntentId('pi_test_transfer_' . uniqid());
        $booking->setPaidAt(new \DateTimeImmutable('-1 week'));
        $booking->setCreatedAt(new \DateTimeImmutable('-1 week'));
        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }

    public function testTransfertApresRetour(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test_driver');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Trajet terminé il y a 3 heures (> 2h après retour)
        $booking = $this->createCompletedTrip($driver, $passenger, new \DateTimeImmutable('-3 hours'));
        $bookingId = $booking->getId();

        // Exécuter le handler manuellement
        $handler = static::getContainer()->get(ProcessTransfersHandler::class);
        $handler(new \App\Message\ProcessTransfersMessage());

        // Vérifier que le transfert a été fait
        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('completed', $bookingFresh->getStatus());
        $this->assertNotNull($bookingFresh->getStripeTransferId());
        $this->assertStringStartsWith('tr_mock_', $bookingFresh->getStripeTransferId());
    }

    public function testPasDeTransfertAvantRetour(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test_driver');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Trajet qui se termine dans 1 heure (pas encore terminé)
        $booking = $this->createCompletedTrip($driver, $passenger, new \DateTimeImmutable('+1 hour'));
        $bookingId = $booking->getId();

        // Exécuter le handler
        $handler = static::getContainer()->get(ProcessTransfersHandler::class);
        $handler(new \App\Message\ProcessTransfersMessage());

        // Le transfert ne doit pas avoir été fait
        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('paid', $bookingFresh->getStatus());
        $this->assertNull($bookingFresh->getStripeTransferId());
    }

    public function testPasDeTransfertMoins2hApresRetour(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test_driver');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Trajet terminé il y a 1 heure (< 2h après retour)
        $booking = $this->createCompletedTrip($driver, $passenger, new \DateTimeImmutable('-1 hour'));
        $bookingId = $booking->getId();

        // Exécuter le handler
        $handler = static::getContainer()->get(ProcessTransfersHandler::class);
        $handler(new \App\Message\ProcessTransfersMessage());

        // Le transfert ne doit pas encore avoir été fait
        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('paid', $bookingFresh->getStatus());
        $this->assertNull($bookingFresh->getStripeTransferId());
    }

    public function testPasDeTransfertSansCompteStripe(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true); // Pas de stripeAccountId
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Trajet terminé il y a 3 heures
        $booking = $this->createCompletedTrip($driver, $passenger, new \DateTimeImmutable('-3 hours'));
        $bookingId = $booking->getId();

        // Exécuter le handler
        $handler = static::getContainer()->get(ProcessTransfersHandler::class);
        $handler(new \App\Message\ProcessTransfersMessage());

        // Le transfert ne doit pas avoir été fait (pas de compte Stripe)
        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('paid', $bookingFresh->getStatus());
        $this->assertNull($bookingFresh->getStripeTransferId());
    }

    public function testPasDeDoubleTransfert(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test_driver');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        // Trajet terminé il y a 3 heures, déjà transféré
        $booking = $this->createCompletedTrip($driver, $passenger, new \DateTimeImmutable('-3 hours'));
        $booking->setStatus('completed');
        $booking->setStripeTransferId('tr_already_done');
        $this->em->flush();
        
        $bookingId = $booking->getId();

        // Exécuter le handler
        $handler = static::getContainer()->get(ProcessTransfersHandler::class);
        $handler(new \App\Message\ProcessTransfersMessage());

        // Le transfert ID ne doit pas avoir changé
        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('tr_already_done', $bookingFresh->getStripeTransferId());
    }

    public function testTransfertMultiplesReservations(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test_driver');
        $passenger1 = $this->createUser('passenger1@test.fr', 'password123', 'Jean', 'Passenger1', true);
        $passenger2 = $this->createUser('passenger2@test.fr', 'password123', 'Claire', 'Passenger2', true);

        // Deux réservations sur un trajet terminé
        $booking1 = $this->createCompletedTrip($driver, $passenger1, new \DateTimeImmutable('-3 hours'));
        $booking1Id = $booking1->getId();

        $booking2 = new Booking();
        $booking2->setTrip($booking1->getTrip());
        $booking2->setPassenger($passenger2);
        $booking2->setSeatsBooked(1);
        $booking2->setPricePerSeat(1000);
        $booking2->setCommissionAmount(150);
        $booking2->setTotalAmount(1150);
        $booking2->setStatus('paid');
        $booking2->setStripePaymentIntentId('pi_test_transfer_' . uniqid());
        $booking2->setPaidAt(new \DateTimeImmutable('-1 week'));
        $booking2->setCreatedAt(new \DateTimeImmutable('-1 week'));
        $this->em->persist($booking2);
        $this->em->flush();
        
        $booking2Id = $booking2->getId();

        // Exécuter le handler
        $handler = static::getContainer()->get(ProcessTransfersHandler::class);
        $handler(new \App\Message\ProcessTransfersMessage());

        // Les deux réservations doivent être transférées
        $booking1Fresh = $this->em->getRepository(Booking::class)->find($booking1Id);
        $booking2Fresh = $this->em->getRepository(Booking::class)->find($booking2Id);

        $this->assertEquals('completed', $booking1Fresh->getStatus());
        $this->assertEquals('completed', $booking2Fresh->getStatus());
        $this->assertNotNull($booking1Fresh->getStripeTransferId());
        $this->assertNotNull($booking2Fresh->getStripeTransferId());
    }
}