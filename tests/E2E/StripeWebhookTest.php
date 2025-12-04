<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Tests\ApiTestCase;

class StripeWebhookTest extends ApiTestCase
{
    private function createTripAndBooking(): Booking
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
        $booking->setStatus('pending');
        $booking->setStripePaymentIntentId('pi_test_webhook_123');
        $booking->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }

    public function testWebhookPaymentIntentSucceeded(): void
    {
        $booking = $this->createTripAndBooking();
        $bookingId = $booking->getId();
        $tripId = $booking->getTrip()->getId();

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_webhook_123',
                    'metadata' => [
                        'booking_id' => (string) $bookingId,
                    ],
                ],
            ],
        ];

        $this->client->request('POST', '/api/webhook/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCode(200);

        // Vérifier que la réservation est passée à paid
        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('paid', $bookingFresh->getStatus());
        $this->assertNotNull($bookingFresh->getPaidAt());

        // Vérifier que les places ont été réduites
        $tripFresh = $this->em->getRepository(Trip::class)->find($tripId);
        $this->assertEquals(2, $tripFresh->getAvailableSeats());
    }

    public function testWebhookPaymentIntentFailed(): void
    {
        $booking = $this->createTripAndBooking();
        $bookingId = $booking->getId();

        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_webhook_123',
                    'metadata' => [
                        'booking_id' => (string) $bookingId,
                    ],
                ],
            ],
        ];

        $this->client->request('POST', '/api/webhook/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCode(200);

        // Vérifier que la réservation est passée à failed
        $bookingFresh = $this->em->getRepository(Booking::class)->find($bookingId);
        $this->assertEquals('failed', $bookingFresh->getStatus());
    }

    public function testWebhookBookingInexistante(): void
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_inexistant',
                    'metadata' => [
                        'booking_id' => '99999',
                    ],
                ],
            ],
        ];

        $this->client->request('POST', '/api/webhook/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        // Le webhook ne doit pas planter, juste ignorer
        $this->assertContains($this->client->getResponse()->getStatusCode(), [200, 400]);
    }

    public function testWebhookEventNonGere(): void
    {
        $payload = [
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'id' => 'cus_test_123',
                ],
            ],
        ];

        $this->client->request('POST', '/api/webhook/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        // Les events non gérés retournent 200 (acknowledge)
        $this->assertResponseStatusCode(200);
    }

    public function testWebhookSansPayload(): void
    {
        $this->client->request('POST', '/api/webhook/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $this->assertContains($this->client->getResponse()->getStatusCode(), [400, 500]);
    }
}