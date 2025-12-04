<?php

namespace App\Tests\E2E;

use App\Entity\User;
use App\Tests\ApiTestCase;

class PasswordResetTest extends ApiTestCase
{
    public function testForgotPasswordEmailExistant(): void
    {
        $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);

        $response = $this->apiRequest('POST', '/api/forgot-password', [
            'email' => 'user@test.fr',
        ], false);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Si cet email existe, vous recevrez un lien de réinitialisation.', $response['message']);

        // Vérifier que le token a été généré
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@test.fr']);
        $this->assertNotNull($user->getResetPasswordToken());
        $this->assertNotNull($user->getResetPasswordTokenExpiresAt());
    }

    public function testForgotPasswordEmailInexistant(): void
    {
        $response = $this->apiRequest('POST', '/api/forgot-password', [
            'email' => 'inexistant@test.fr',
        ], false);

        // Même message (sécurité)
        $this->assertResponseStatusCode(200);
        $this->assertEquals('Si cet email existe, vous recevrez un lien de réinitialisation.', $response['message']);
    }

    public function testForgotPasswordSansEmail(): void
    {
        $response = $this->apiRequest('POST', '/api/forgot-password', [], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Email requis', $response['error']);
    }

    public function testVerifyTokenValide(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        $user->setResetPasswordToken('valid_token_123');
        $user->setResetPasswordTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $this->client->request('GET', '/api/reset-password/verify/valid_token_123');

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['valid']);
    }

    public function testVerifyTokenInvalide(): void
    {
        $this->client->request('GET', '/api/reset-password/verify/token_inexistant');

        $this->assertResponseStatusCode(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($response['valid']);
        $this->assertEquals('Token invalide', $response['error']);
    }

    public function testVerifyTokenExpire(): void
    {
        $user = $this->createUser('user@test.fr', 'password123', 'Jean', 'User', true);
        $user->setResetPasswordToken('expired_token_123');
        $user->setResetPasswordTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $this->client->request('GET', '/api/reset-password/verify/expired_token_123');

        $this->assertResponseStatusCode(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($response['valid']);
        $this->assertEquals('Token expiré', $response['error']);
    }

    public function testResetPasswordSucces(): void
    {
        $user = $this->createUser('user@test.fr', 'oldpassword', 'Jean', 'User', true);
        $user->setResetPasswordToken('reset_token_123');
        $user->setResetPasswordTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $response = $this->apiRequest('POST', '/api/reset-password', [
            'token' => 'reset_token_123',
            'password' => 'newpassword123',
        ], false);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Mot de passe modifié avec succès', $response['message']);

        // Vérifier que le token est supprimé
        $userFresh = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@test.fr']);
        $this->assertNull($userFresh->getResetPasswordToken());
        $this->assertNull($userFresh->getResetPasswordTokenExpiresAt());

        // Vérifier la connexion avec le nouveau mot de passe
        $this->login('user@test.fr', 'newpassword123');
        $this->assertNotNull($this->token);
    }

    public function testResetPasswordTokenInvalide(): void
    {
        $response = $this->apiRequest('POST', '/api/reset-password', [
            'token' => 'invalid_token',
            'password' => 'newpassword123',
        ], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Token invalide', $response['error']);
    }

    public function testResetPasswordTokenExpire(): void
    {
        $user = $this->createUser('user@test.fr', 'oldpassword', 'Jean', 'User', true);
        $user->setResetPasswordToken('expired_token');
        $user->setResetPasswordTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $response = $this->apiRequest('POST', '/api/reset-password', [
            'token' => 'expired_token',
            'password' => 'newpassword123',
        ], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Token expiré', $response['error']);
    }

    public function testResetPasswordTropCourt(): void
    {
        $user = $this->createUser('user@test.fr', 'oldpassword', 'Jean', 'User', true);
        $user->setResetPasswordToken('reset_token');
        $user->setResetPasswordTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $response = $this->apiRequest('POST', '/api/reset-password', [
            'token' => 'reset_token',
            'password' => '123',
        ], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Mot de passe trop court (minimum 6 caractères)', $response['error']);
    }

    public function testResetPasswordSansToken(): void
    {
        $response = $this->apiRequest('POST', '/api/reset-password', [
            'password' => 'newpassword123',
        ], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Token et mot de passe requis', $response['error']);
    }

    public function testResetPasswordSansPassword(): void
    {
        $response = $this->apiRequest('POST', '/api/reset-password', [
            'token' => 'some_token',
        ], false);

        $this->assertResponseStatusCode(400);
        $this->assertEquals('Token et mot de passe requis', $response['error']);
    }
}