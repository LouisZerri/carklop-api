<?php

namespace App\Tests\E2E;

use App\Entity\User;
use App\Tests\ApiTestCase;

class SocialAuthTest extends ApiTestCase
{
    // ===== GOOGLE =====

    public function testGoogleAuthSansToken(): void
    {
        $response = $this->apiRequest('POST', '/api/auth/google', [], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('id_token requis', $response['error']);
    }

    public function testGoogleAuthTokenInvalide(): void
    {
        $response = $this->apiRequest('POST', '/api/auth/google', [
            'id_token' => 'token_invalide',
        ], false);

        $this->assertResponseStatusCode(401);
        $this->assertEquals('Token Google invalide', $response['error']);
    }

    public function testGoogleAuthLieCompteExistant(): void
    {
        // Créer un utilisateur existant
        $user = $this->createUser('existing@gmail.com', 'password123', 'Jean', 'Existant', true);
        $userId = $user->getId();

        // Simuler une connexion Google avec le même email
        // Note: En vrai, on ne peut pas tester avec un vrai token Google
        // On vérifie juste que l'utilisateur existant n'a pas de googleId
        $userFresh = $this->em->getRepository(User::class)->find($userId);
        $this->assertNull($userFresh->getGoogleId());
    }

    // ===== APPLE =====

    public function testAppleAuthSansToken(): void
    {
        $response = $this->apiRequest('POST', '/api/auth/apple', [], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('id_token requis', $response['error']);
    }

    public function testAppleAuthTokenInvalide(): void
    {
        $response = $this->apiRequest('POST', '/api/auth/apple', [
            'id_token' => 'token_invalide',
        ], false);

        $this->assertResponseStatusCode(401);
        $this->assertEquals('Token Apple invalide', $response['error']);
    }

    public function testAppleAuthAvecNom(): void
    {
        // Apple envoie le nom seulement à la première connexion
        // On vérifie que le endpoint accepte les paramètres
        $response = $this->apiRequest('POST', '/api/auth/apple', [
            'id_token' => 'token_invalide',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ], false);

        // Token invalide mais les paramètres sont acceptés
        $this->assertResponseStatusCode(401);
    }

    // ===== CHAMPS USER =====

    public function testUserAChampsSociaux(): void
    {
        $user = $this->createUser('test@test.fr', 'password123', 'Test', 'User', true);

        // Vérifier que les champs existent
        $this->assertNull($user->getGoogleId());
        $this->assertNull($user->getAppleId());

        // Définir les IDs
        $user->setGoogleId('google_123');
        $user->setAppleId('apple_456');
        $this->em->flush();

        $userFresh = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertEquals('google_123', $userFresh->getGoogleId());
        $this->assertEquals('apple_456', $userFresh->getAppleId());
    }
}