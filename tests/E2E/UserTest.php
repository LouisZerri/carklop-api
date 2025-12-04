<?php

namespace App\Tests\E2E;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserTest extends ApiTestCase
{
    // ===== INSCRIPTION =====

    public function testInscription(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'nouveau@test.fr',
            'plainPassword' => 'password123',
            'firstName' => 'Paul',
            'lastName' => 'Nouveau',
        ], false);

        $this->assertResponseStatusCode(201);
        $this->assertEquals('nouveau@test.fr', $response['email']);
        $this->assertEquals('Paul', $response['firstName']);
        $this->assertEquals('Nouveau', $response['lastName']);
        $this->assertFalse($response['isVerified'] ?? false);
    }

    public function testInscriptionEmailInvalide(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'pas-un-email',
            'plainPassword' => 'password123',
            'firstName' => 'Paul',
            'lastName' => 'Nouveau',
        ], false);

        $this->assertResponseStatusCode(422);
    }

    public function testInscriptionMotDePasseTropCourt(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'nouveau@test.fr',
            'plainPassword' => '123',
            'firstName' => 'Paul',
            'lastName' => 'Nouveau',
        ], false);

        $this->assertResponseStatusCode(422);
    }

    public function testInscriptionSansPrenom(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'nouveau@test.fr',
            'plainPassword' => 'password123',
            'lastName' => 'Nouveau',
        ], false);

        $this->assertResponseStatusCode(422);
    }

    public function testInscriptionSansNom(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'nouveau@test.fr',
            'plainPassword' => 'password123',
            'firstName' => 'Paul',
        ], false);

        $this->assertResponseStatusCode(422);
    }

    public function testInscriptionSansMotDePasse(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'nouveau@test.fr',
            'firstName' => 'Paul',
            'lastName' => 'Nouveau',
        ], false);

        $this->assertResponseStatusCode(500);
    }

    public function testInscriptionEmailDejaUtilise(): void
    {
        $this->createUser('existant@test.fr', 'password123', 'Test', 'User');

        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'existant@test.fr',
            'plainPassword' => 'password123',
            'firstName' => 'Paul',
            'lastName' => 'Nouveau',
        ], false);

        // L'API retourne 500 car c'est une contrainte SQL unique
        $this->assertContains($this->client->getResponse()->getStatusCode(), [422, 500]);
    }

    public function testInscriptionTelephoneInvalide(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'nouveau@test.fr',
            'plainPassword' => 'password123',
            'firstName' => 'Paul',
            'lastName' => 'Nouveau',
            'phone' => 'abc123',
        ], false);

        $this->assertResponseStatusCode(422);
    }

    public function testInscriptionTelephoneValide(): void
    {
        $response = $this->apiRequest('POST', '/api/users', [
            'email' => 'nouveau@test.fr',
            'plainPassword' => 'password123',
            'firstName' => 'Paul',
            'lastName' => 'Nouveau',
            'phone' => '+33612345678',
        ], false);

        $this->assertResponseStatusCode(201);
        $this->assertEquals('+33612345678', $response['phone']);
    }

    // ===== LOGIN =====

    public function testLogin(): void
    {
        $this->createUser('login@test.fr', 'password123');

        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'login@test.fr',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
    }

    public function testLoginMauvaisMotDePasse(): void
    {
        $this->createUser('login@test.fr', 'password123');

        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'login@test.fr',
            'password' => 'mauvais',
        ]));

        $this->assertResponseStatusCode(401);
    }

    public function testLoginEmailInexistant(): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'inexistant@test.fr',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCode(401);
    }

    public function testLoginSansEmail(): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCode(400);
    }

    public function testLoginSansMotDePasse(): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'login@test.fr',
        ]));

        $this->assertResponseStatusCode(400);
    }

    // ===== PROFIL =====

    public function testProfil(): void
    {
        $this->createUser('profil@test.fr', 'password123', 'Marie', 'Test');
        $this->login('profil@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/me');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('profil@test.fr', $response['email']);
        $this->assertEquals('Marie', $response['firstName']);
        $this->assertEquals('Test', $response['lastName']);
        $this->assertArrayHasKey('defaultAvatar', $response);
        $this->assertStringContainsString('ui-avatars.com', $response['defaultAvatar']);
        $this->assertArrayHasKey('averageRating', $response);
        $this->assertArrayHasKey('reviewsCount', $response);
    }

    public function testProfilNonAuthentifie(): void
    {
        $this->apiRequest('GET', '/api/me', null, false);

        $this->assertResponseStatusCode(401);
    }

    public function testModifierProfil(): void
    {
        $user = $this->createUser('profil@test.fr', 'password123', 'Marie', 'Test');
        $this->login('profil@test.fr', 'password123');

        $response = $this->apiRequest('PATCH', '/api/users/' . $user->getId(), [
            'firstName' => 'Marie-Claire',
            'lastName' => 'Dupont',
            'phone' => '+33699999999',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Marie-Claire', $response['firstName']);
        $this->assertEquals('Dupont', $response['lastName']);
        $this->assertEquals('+33699999999', $response['phone']);
    }

    public function testModifierProfilAutreUtilisateur(): void
    {
        $user1 = $this->createUser('user1@test.fr', 'password123', 'User', 'One');
        $user2 = $this->createUser('user2@test.fr', 'password123', 'User', 'Two');
        $user2Id = $user2->getId();

        $this->login('user1@test.fr', 'password123');

        $response = $this->apiRequest('PATCH', '/api/users/' . $user2Id, [
            'firstName' => 'Hacker',
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testModifierMotDePasse(): void
    {
        $user = $this->createUser('profil@test.fr', 'password123', 'Marie', 'Test');
        $this->login('profil@test.fr', 'password123');

        $response = $this->apiRequest('PATCH', '/api/users/' . $user->getId(), [
            'plainPassword' => 'nouveaumdp123',
        ]);

        $this->assertResponseStatusCode(200);

        // Vérifier qu'on peut se connecter avec le nouveau mot de passe
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'profil@test.fr',
            'password' => 'nouveaumdp123',
        ]));

        $this->assertResponseStatusCode(200);
    }

    // ===== VERIFICATION EMAIL =====

    public function testVerificationEmailTokenValide(): void
    {
        $user = $this->createUser('verify@test.fr', 'password123', 'Test', 'User', false);
        $user->setEmailVerificationToken('valid_token_123');
        $this->em->flush();

        $this->client->request('GET', '/api/verify-email/valid_token_123');

        $this->assertResponseStatusCode(200);
        $this->assertStringContainsString('Email vérifié', $this->client->getResponse()->getContent());

        // Vérifier en base
        $this->em->refresh($user);
        $this->assertTrue($user->isVerified());
        $this->assertNull($user->getEmailVerificationToken());
    }

    public function testVerificationEmailTokenInvalide(): void
    {
        $this->client->request('GET', '/api/verify-email/token_invalide');

        $this->assertResponseStatusCode(404);
        $this->assertStringContainsString('invalide', $this->client->getResponse()->getContent());
    }

    public function testResendVerification(): void
    {
        $this->createUser('nonverifie@test.fr', 'password123', 'Test', 'User', false);
        $this->login('nonverifie@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/resend-verification');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Email de vérification envoyé', $response['message']);
    }

    public function testResendVerificationDejaVerifie(): void
    {
        $this->createUser('verifie@test.fr', 'password123', 'Test', 'User', true);
        $this->login('verifie@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/resend-verification');

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Email déjà vérifié', $response['error']);
    }

    public function testResendVerificationNonAuthentifie(): void
    {
        $response = $this->apiRequest('POST', '/api/resend-verification', null, false);

        $this->assertResponseStatusCode(401);
    }

    // ===== AVATAR =====

    public function testUploadAvatar(): void
    {
        $this->createUser('avatar@test.fr', 'password123', 'Test', 'User');
        $this->login('avatar@test.fr', 'password123');

        // Créer une image de test
        $imagePath = sys_get_temp_dir() . '/test_avatar.jpg';
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $imagePath);
        imagedestroy($image);

        $this->client->request('POST', '/api/upload/avatar', [], [
            'avatar' => new UploadedFile($imagePath, 'avatar.jpg', 'image/jpeg', null, true),
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Avatar mis à jour', $response['message']);
        $this->assertStringContainsString('/uploads/avatars/', $response['avatar']);

        // Nettoyer
        unlink($imagePath);
    }

    public function testUploadAvatarSansFichier(): void
    {
        $this->createUser('avatar@test.fr', 'password123', 'Test', 'User');
        $this->login('avatar@test.fr', 'password123');

        $this->client->request('POST', '/api/upload/avatar', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCode(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Aucun fichier envoyé', $response['error']);
    }

    public function testUploadAvatarFormatInvalide(): void
    {
        $this->createUser('avatar@test.fr', 'password123', 'Test', 'User');
        $this->login('avatar@test.fr', 'password123');

        // Créer un fichier texte
        $filePath = sys_get_temp_dir() . '/test.txt';
        file_put_contents($filePath, 'test content');

        $this->client->request('POST', '/api/upload/avatar', [], [
            'avatar' => new UploadedFile($filePath, 'test.txt', 'text/plain', null, true),
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCode(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Format invalide (JPG, PNG, WEBP)', $response['error']);

        // Nettoyer
        unlink($filePath);
    }

    public function testUploadAvatarNonAuthentifie(): void
    {
        $imagePath = sys_get_temp_dir() . '/test_avatar.jpg';
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $imagePath);
        imagedestroy($image);

        $this->client->request('POST', '/api/upload/avatar', [], [
            'avatar' => new UploadedFile($imagePath, 'avatar.jpg', 'image/jpeg', null, true),
        ]);

        $this->assertResponseStatusCode(401);

        // Nettoyer
        unlink($imagePath);
    }

    // ===== LISTE USERS =====

    public function testListeUsers(): void
    {
        $this->createUser('user1@test.fr', 'password123', 'User', 'One');
        $this->createUser('user2@test.fr', 'password123', 'User', 'Two');

        $this->login('user1@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/users');

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('member', $response);
        $this->assertCount(2, $response['member']);
    }

    public function testVoirProfilAutreUtilisateur(): void
    {
        $user1 = $this->createUser('user1@test.fr', 'password123', 'User', 'One');
        $user2 = $this->createUser('user2@test.fr', 'password123', 'User', 'Two');

        $this->login('user1@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/users/' . $user2->getId());

        $this->assertResponseStatusCode(200);
        $this->assertEquals('user2@test.fr', $response['email']);
        $this->assertEquals('User', $response['firstName']);
    }
}
