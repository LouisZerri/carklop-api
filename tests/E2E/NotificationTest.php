<?php

namespace App\Tests\E2E;

use App\Entity\DeviceToken;
use App\Entity\Notification;
use App\Tests\ApiTestCase;

class NotificationTest extends ApiTestCase
{
    public function testEnregistrerToken(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/notifications/register-token', [
            'token' => 'ExponentPushToken[xxxxxxxxxxxxx]',
            'platform' => 'ios',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertEquals('Token enregistré', $response['message']);

        // Vérifier en base
        $deviceToken = $this->em->getRepository(DeviceToken::class)->findOneBy([
            'user' => $user,
            'token' => 'ExponentPushToken[xxxxxxxxxxxxx]',
        ]);
        $this->assertNotNull($deviceToken);
        $this->assertEquals('ios', $deviceToken->getPlatform());
    }

    public function testEnregistrerTokenDejaExistant(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        
        $token = new DeviceToken();
        $token->setUser($user);
        $token->setToken('ExponentPushToken[xxxxxxxxxxxxx]');
        $token->setPlatform('ios');
        $token->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($token);
        $this->em->flush();

        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/notifications/register-token', [
            'token' => 'ExponentPushToken[xxxxxxxxxxxxx]',
            'platform' => 'ios',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Token déjà enregistré', $response['message']);
    }

    public function testEnregistrerTokenPlatformInvalide(): void
    {
        $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/notifications/register-token', [
            'token' => 'ExponentPushToken[xxxxxxxxxxxxx]',
            'platform' => 'windows',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Platform invalide (ios ou android)', $response['error']);
    }

    public function testEnregistrerTokenSansToken(): void
    {
        $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/notifications/register-token', [
            'platform' => 'ios',
        ]);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('token et platform requis', $response['error']);
    }

    public function testEnregistrerTokenNonAuthentifie(): void
    {
        $response = $this->apiRequest('POST', '/api/notifications/register-token', [
            'token' => 'ExponentPushToken[xxxxxxxxxxxxx]',
            'platform' => 'ios',
        ], false);

        $this->assertResponseStatusCode(401);
    }

    public function testListerNotifications(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);

        $notif1 = new Notification();
        $notif1->setUser($user);
        $notif1->setTitle('Nouvelle réservation');
        $notif1->setBody('Jean a réservé 2 places');
        $notif1->setType('booking_new');
        $notif1->setIsRead(false);
        $notif1->setCreatedAt(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($notif1);

        $notif2 = new Notification();
        $notif2->setUser($user);
        $notif2->setTitle('Nouvel avis');
        $notif2->setBody('Marie vous a donné 5 étoiles');
        $notif2->setType('review_received');
        $notif2->setIsRead(true);
        $notif2->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($notif2);

        $this->em->flush();

        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/notifications');

        $this->assertResponseStatusCode(200);
        $this->assertCount(2, $response);
        $this->assertEquals('Nouvel avis', $response[0]['title']); // Plus récent en premier
        $this->assertEquals('Nouvelle réservation', $response[1]['title']);
    }

    public function testListerNotificationsNonAuthentifie(): void
    {
        $response = $this->apiRequest('GET', '/api/notifications', null, false);

        $this->assertResponseStatusCode(401);
    }

    public function testMarquerCommeLue(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);

        $notif = new Notification();
        $notif->setUser($user);
        $notif->setTitle('Test');
        $notif->setBody('Test body');
        $notif->setType('test');
        $notif->setIsRead(false);
        $notif->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($notif);
        $this->em->flush();

        $notifId = $notif->getId();

        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/notifications/' . $notifId . '/read');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Notification lue', $response['message']);

        // Vérifier en base
        $notifFresh = $this->em->getRepository(Notification::class)->find($notifId);
        $this->assertTrue($notifFresh->isRead());
    }

    public function testMarquerCommeLueAutreUtilisateur(): void
    {
        $user1 = $this->createUser('user1@test.fr', 'password123', 'Jean', 'User1', true);
        $user2 = $this->createUser('user2@test.fr', 'password123', 'Marie', 'User2', true);

        $notif = new Notification();
        $notif->setUser($user1);
        $notif->setTitle('Test');
        $notif->setBody('Test body');
        $notif->setType('test');
        $notif->setIsRead(false);
        $notif->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($notif);
        $this->em->flush();

        $notifId = $notif->getId();

        $this->login('user2@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/notifications/' . $notifId . '/read');

        $this->assertResponseStatusCode(404);
    }

    public function testMarquerToutesCommeLues(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);

        for ($i = 0; $i < 3; $i++) {
            $notif = new Notification();
            $notif->setUser($user);
            $notif->setTitle('Test ' . $i);
            $notif->setBody('Test body');
            $notif->setType('test');
            $notif->setIsRead(false);
            $notif->setCreatedAt(new \DateTimeImmutable());
            $this->em->persist($notif);
        }
        $this->em->flush();

        $this->login('user@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/notifications/read-all');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Toutes les notifications lues', $response['message']);

        // Vérifier en base
        $unreadCount = $this->em->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $this->assertEquals(0, $unreadCount);
    }
}