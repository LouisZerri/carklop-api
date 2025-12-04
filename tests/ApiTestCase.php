<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected $client;
    protected EntityManagerInterface $em;
    protected ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->cleanDatabase();
    }

    protected function cleanDatabase(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $tables = ['message', 'conversation', 'review', 'notification', 'device_token', 'booking', 'trip', 'user'];
        foreach ($tables as $table) {
            $connection->executeStatement("TRUNCATE TABLE `$table`");
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function createUser(
        string $email = 'test@test.fr',
        string $password = 'password123',
        string $firstName = 'Test',
        string $lastName = 'User',
        bool $isVerified = true,
        ?string $stripeAccountId = null
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setIsVerified($isVerified);
        $user->setStripeAccountId($stripeAccountId);
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function login(string $email = 'test@test.fr', string $password = 'password123'): ?string
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->token = $response['token'] ?? null;

        return $this->token;
    }

    protected function apiRequest(
        string $method,
        string $uri,
        ?array $data = null,
        bool $authenticated = true
    ): array {
        $contentType = ($method === 'PATCH') ? 'application/merge-patch+json' : 'application/ld+json';
        $headers = ['CONTENT_TYPE' => $contentType];

        if ($authenticated && $this->token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
        }

        $this->client->request($method, $uri, [], [], $headers, $data ? json_encode($data) : null);

        $content = $this->client->getResponse()->getContent();
        return json_decode($content, true) ?? [];
    }

    protected function assertResponseStatusCode(int $expectedCode): void
    {
        $this->assertEquals($expectedCode, $this->client->getResponse()->getStatusCode());
    }
}
