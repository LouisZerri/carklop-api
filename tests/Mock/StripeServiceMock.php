<?php

namespace App\Tests\Mock;

use App\Entity\Booking;
use App\Entity\User;
use App\Service\StripeService;

class StripeServiceMock extends StripeService
{
    public function __construct()
    {
        // Ne pas appeler le parent - on mocke tout
    }

    public function createConnectAccount(User $user): string
    {
        return 'acct_mock_' . uniqid();
    }

    public function createOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): string
    {
        return 'https://connect.stripe.com/mock-onboarding';
    }

    public function isAccountActive(string $accountId): bool
    {
        return true;
    }

    public function createPaymentIntent(Booking $booking): object
    {
        return (object) [
            'id' => 'pi_mock_' . uniqid(),
            'client_secret' => 'cs_mock_' . uniqid(),
        ];
    }

    public function refund(string $paymentIntentId, int $amount): object
    {
        return (object) [
            'id' => 're_mock_' . uniqid(),
            'amount' => $amount,
        ];
    }

    public function transferToDriver(Booking $booking, ?int $amount = null): object
    {
        return (object) [
            'id' => 'tr_mock_' . uniqid(),
            'amount' => $amount ?? ($booking->getPricePerSeat() * $booking->getSeatsBooked()),
        ];
    }
}