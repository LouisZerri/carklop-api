<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Booking;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Transfer;

class StripeService
{
    public function __construct(
        private string $stripeSecretKey
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Crée un compte Connect pour un conducteur
     */
    public function createConnectAccount(User $user): string
    {
        $account = Account::create([
            'type' => 'express',
            'country' => 'FR',
            'email' => $user->getEmail(),
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
        ]);

        return $account->id;
    }

    /**
     * Génère le lien d'onboarding Stripe Connect
     */
    public function createOnboardingLink(string $accountId, string $returnUrl, string $refreshUrl): string
    {
        $accountLink = AccountLink::create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return $accountLink->url;
    }

    /**
     * Vérifie si le compte Connect est actif
     */
    public function isAccountActive(string $accountId): bool
    {
        $account = Account::retrieve($accountId);
        return $account->charges_enabled && $account->payouts_enabled;
    }

    /**
     * Crée un PaymentIntent pour une réservation
     */
    public function createPaymentIntent(Booking $booking): object
    {
        return PaymentIntent::create([
            'amount' => $booking->getTotalAmount(),
            'currency' => 'eur',
            'metadata' => [
                'booking_id' => $booking->getId(),
            ],
        ]);
    }

    /**
     * Rembourse un paiement (total ou partiel)
     */
    public function refund(string $paymentIntentId, int $amount): object
    {
        return Refund::create([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
        ]);
    }

    /**
     * Transfère l'argent au conducteur
     */
    public function transferToDriver(Booking $booking, ?int $amount = null): object
    {
        $trip = $booking->getTrip();
        $driver = $trip->getDriver();

        // Si pas de montant spécifié, on prend le montant total du conducteur
        if ($amount === null) {
            $amount = $booking->getPricePerSeat() * $booking->getSeatsBooked();
        }

        return Transfer::create([
            'amount' => $amount,
            'currency' => 'eur',
            'destination' => $driver->getStripeAccountId(),
            'metadata' => [
                'booking_id' => $booking->getId(),
            ],
        ]);
    }
}