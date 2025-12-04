<?php

namespace App\Tests\E2E;

use App\Entity\Booking;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Trip;
use App\Tests\ApiTestCase;

class MessageTest extends ApiTestCase
{
    private function createTripAndBooking($driver, $passenger): array
    {
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
        $booking->setStatus('paid');
        $booking->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);

        $this->em->flush();

        return [$trip, $booking];
    }

    private function createConversation($booking, $driver, $passenger): Conversation
    {
        $conversation = new Conversation();
        $conversation->setBooking($booking);
        $conversation->setDriver($driver);
        $conversation->setPassenger($passenger);
        $conversation->setCreatedAt(new \DateTimeImmutable());
        $conversation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($conversation);
        $this->em->flush();

        return $conversation;
    }

    public function testDemarrerConversation(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/messages/start/' . $booking->getId());

        $this->assertResponseStatusCode(201);
        $this->assertArrayHasKey('conversation_id', $response);
        $this->assertEquals('Conversation créée', $response['message']);
    }

    public function testDemarrerConversationDejaExistante(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);
        $conversation = $this->createConversation($booking, $driver, $passenger);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/messages/start/' . $booking->getId());

        $this->assertResponseStatusCode(200);
        $this->assertEquals($conversation->getId(), $response['conversation_id']);
        $this->assertEquals('Conversation existante', $response['message']);
    }

    public function testDemarrerConversationNonAutorise(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $otherUser = $this->createUser('other@test.fr', 'password123', 'Other', 'User', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);

        $this->login('other@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/messages/start/' . $booking->getId());

        $this->assertResponseStatusCode(403);
    }

    public function testEnvoyerMessage(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);
        $conversation = $this->createConversation($booking, $driver, $passenger);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/messages/conversations/' . $conversation->getId() . '/send', [
            'content' => 'Bonjour, à quelle heure le RDV ?',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertArrayHasKey('message_id', $response);
        $this->assertEquals('Bonjour, à quelle heure le RDV ?', $response['content']);
    }

    public function testEnvoyerMessageVide(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);
        $conversation = $this->createConversation($booking, $driver, $passenger);

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/messages/conversations/' . $conversation->getId() . '/send', [
            'content' => '',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Message vide', $response['error']);
    }

    public function testEnvoyerMessageNonAutorise(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);
        $otherUser = $this->createUser('other@test.fr', 'password123', 'Other', 'User', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);
        $conversation = $this->createConversation($booking, $driver, $passenger);

        $this->login('other@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/messages/conversations/' . $conversation->getId() . '/send', [
            'content' => 'Message non autorisé',
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testListerMessages(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);
        $conversation = $this->createConversation($booking, $driver, $passenger);

        // Créer des messages
        $message1 = new Message();
        $message1->setConversation($conversation);
        $message1->setSender($passenger);
        $message1->setContent('Bonjour !');
        $message1->setIsRead(false);
        $message1->setCreatedAt(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($message1);

        $message2 = new Message();
        $message2->setConversation($conversation);
        $message2->setSender($driver);
        $message2->setContent('Bonjour, ça va ?');
        $message2->setIsRead(false);
        $message2->setCreatedAt(new \DateTimeImmutable('-30 minutes'));
        $this->em->persist($message2);

        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/messages/conversations/' . $conversation->getId());

        $this->assertResponseStatusCode(200);
        $this->assertCount(2, $response);
        $this->assertEquals('Bonjour !', $response[0]['content']);
        $this->assertTrue($response[0]['isMe']);
        $this->assertEquals('Bonjour, ça va ?', $response[1]['content']);
        $this->assertFalse($response[1]['isMe']);
    }

    public function testListerConversations(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);
        $conversation = $this->createConversation($booking, $driver, $passenger);

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($driver);
        $message->setContent('Dernier message');
        $message->setIsRead(false);
        $message->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($message);
        $this->em->flush();

        $this->login('passenger@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/messages/conversations');

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $response);
        $this->assertEquals('Marie', $response[0]['otherUser']['firstName']);
        $this->assertEquals('Dernier message', $response[0]['lastMessage']['content']);
        $this->assertEquals(1, $response[0]['unreadCount']);
    }

        public function testMarquerMessagesCommeLus(): void
    {
        $driver = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test');
        $passenger = $this->createUser('passenger@test.fr', 'password123', 'Jean', 'Passenger', true);

        [$trip, $booking] = $this->createTripAndBooking($driver, $passenger);
        $conversation = $this->createConversation($booking, $driver, $passenger);

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($driver);
        $message->setContent('Message non lu');
        $message->setIsRead(false);
        $message->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($message);
        $this->em->flush();

        $messageId = $message->getId();
        $conversationId = $conversation->getId();

        $this->login('passenger@test.fr', 'password123');

        // Lire les messages (marque automatiquement comme lu)
        $this->apiRequest('GET', '/api/messages/conversations/' . $conversationId);

        // Recharger depuis la base
        $messageFresh = $this->em->getRepository(\App\Entity\Message::class)->find($messageId);
        $this->assertTrue($messageFresh->isRead());
    }
}