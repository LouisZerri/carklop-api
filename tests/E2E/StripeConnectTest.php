<?php

namespace App\Tests\E2E;

use App\Tests\ApiTestCase;

class StripeConnectTest extends ApiTestCase
{
    public function testOnboardingCreeCompteConnect(): void
    {
        $user = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true);
        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/stripe/connect/onboarding', [
            'return_url' => 'https://production.fr/onboarding/success',
            'refresh_url' => 'https://production.fr/onboarding/refresh',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('onboarding_url', $response);
        $this->assertStringContainsString('stripe.com', $response['onboarding_url']);

        // Vérifier que le compte Stripe est enregistré
        $userFresh = $this->em->getRepository(\App\Entity\User::class)->find($user->getId());
        $this->assertNotNull($userFresh->getStripeAccountId());
        $this->assertStringStartsWith('acct_mock_', $userFresh->getStripeAccountId());
    }

    public function testOnboardingDejaCompteConnect(): void
    {
        $user = $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_existing_123');
        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/stripe/connect/onboarding', [
            'return_url' => 'https://production.fr/onboarding/success',
            'refresh_url' => 'https://production.fr/onboarding/refresh',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('onboarding_url', $response);

        // Le compte existant ne doit pas être écrasé
        $userFresh = $this->em->getRepository(\App\Entity\User::class)->find($user->getId());
        $this->assertEquals('acct_existing_123', $userFresh->getStripeAccountId());
    }

    public function testOnboardingNonAuthentifie(): void
    {
        $response = $this->apiRequest('POST', '/api/stripe/connect/onboarding', [
            'return_url' => 'https://production.fr/onboarding/success',
            'refresh_url' => 'https://production.fr/onboarding/refresh',
        ], false);

        $this->assertResponseStatusCode(401);
    }

    public function testOnboardingSansUrls(): void
    {
        $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true);
        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('POST', '/api/stripe/connect/onboarding', []);

        $this->assertResponseStatusCode(400);
    }

    public function testStatusCompteActif(): void
    {
        $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true, 'acct_test_123');
        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/stripe/connect/status');

        $this->assertResponseStatusCode(200);
        $this->assertTrue($response['account_active']);
        $this->assertEquals('acct_test_123', $response['account_id']);
    }

    public function testStatusSansCompte(): void
    {
        $this->createUser('driver@test.fr', 'password123', 'Marie', 'Driver', true);
        $this->login('driver@test.fr', 'password123');

        $response = $this->apiRequest('GET', '/api/stripe/connect/status');

        $this->assertResponseStatusCode(200);
        $this->assertFalse($response['account_active']);
        $this->assertNull($response['account_id']);
    }
}