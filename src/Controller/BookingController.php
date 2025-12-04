<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Conversation;
use App\Entity\SavingsEstimate;
use App\Entity\Trip;
use App\Entity\User;
use App\Service\NotificationService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/bookings')]
class BookingController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $em,
        private NotificationService $notificationService
    ) {}

    /**
     * Créer une réservation et initialiser le paiement
     */
    #[Route('/create', name: 'booking_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // Vérifier que l'email est validé
        if (!$user->isVerified()) {
            return new JsonResponse(['error' => 'Vous devez vérifier votre email avant de réserver.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        // Validation basique
        if (!isset($data['trip_id']) || !isset($data['seats'])) {
            return new JsonResponse(['error' => 'trip_id et seats requis'], 400);
        }

        $trip = $this->em->getRepository(Trip::class)->find($data['trip_id']);

        if (!$trip) {
            return new JsonResponse(['error' => 'Trajet introuvable'], 404);
        }

        if ($trip->getStatus() !== 'published') {
            return new JsonResponse(['error' => 'Trajet non disponible'], 400);
        }

        if ($trip->getDriver() === $user) {
            return new JsonResponse(['error' => 'Vous ne pouvez pas réserver votre propre trajet'], 400);
        }

        $seats = (int) $data['seats'];

        if ($seats < 1 || $seats > $trip->getAvailableSeats()) {
            return new JsonResponse(['error' => 'Nombre de places invalide'], 400);
        }

        // Calcul des montants (en centimes)
        $pricePerSeat = $trip->getPricePerSeat();
        $subtotal = $pricePerSeat * $seats;
        $commission = (int) round($subtotal * 0.15);
        $totalAmount = $subtotal + $commission;

        // Créer la réservation
        $booking = new Booking();
        $booking->setTrip($trip);
        $booking->setPassenger($user);
        $booking->setSeatsBooked($seats);
        $booking->setPricePerSeat($pricePerSeat);
        $booking->setCommissionAmount($commission);
        $booking->setTotalAmount($totalAmount);
        $booking->setStatus('pending');
        $booking->setCreatedAt(new \DateTimeImmutable());

        // Budget et économies estimées
        if (isset($data['estimated_budget'])) {
            $estimatedBudget = (int) $data['estimated_budget'];
            $booking->setEstimatedBudget($estimatedBudget);

            // Calculer les économies basées sur le pays de destination
            $destinationCountry = $trip->getDestinationCountry();
            $savingsEstimate = $this->em->getRepository(SavingsEstimate::class)
                ->findOneBy(['countryCode' => $destinationCountry]);

            if ($savingsEstimate && $estimatedBudget > 0) {
                $avgPercent = ($savingsEstimate->getAlimentaire() + $savingsEstimate->getAlcool() 
                    + $savingsEstimate->getCarburant() + $savingsEstimate->getTabac()) / 4;
                $estimatedSavings = (int) round($estimatedBudget * abs($avgPercent) / 100);
                $booking->setEstimatedSavings($estimatedSavings);
            }
        }

        $this->em->persist($booking);
        $this->em->flush();

        // Créer le PaymentIntent Stripe
        try {
            $paymentIntent = $this->stripeService->createPaymentIntent($booking);
            $booking->setStripePaymentIntentId($paymentIntent->id);
            $this->em->flush();

            return new JsonResponse([
                'booking_id' => $booking->getId(),
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $totalAmount,
                'commission' => $commission,
                'subtotal' => $subtotal,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur Stripe, supprimer la réservation
            $this->em->remove($booking);
            $this->em->flush();

            return new JsonResponse(['error' => 'Erreur paiement: ' . $e->getMessage()], 500);
        }
    }
    
    #[Route('/{id}/confirm', name: 'booking_confirm', methods: ['POST'])]
    public function confirm(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $booking = $this->em->getRepository(Booking::class)->find($id);

        if (!$booking) {
            return new JsonResponse(['error' => 'Réservation introuvable'], 404);
        }

        if ($booking->getPassenger() !== $user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        if ($booking->getStatus() !== 'pending') {
            return new JsonResponse(['error' => 'Réservation déjà traitée'], 400);
        }

        // Mettre à jour la réservation
        $booking->setStatus('paid');
        $booking->setPaidAt(new \DateTimeImmutable());

        // Réduire les places disponibles
        $trip = $booking->getTrip();
        $trip->setAvailableSeats($trip->getAvailableSeats() - $booking->getSeatsBooked());

        // Créer la conversation automatiquement
        $conversation = new Conversation();
        $conversation->setBooking($booking);
        $conversation->setDriver($trip->getDriver());
        $conversation->setPassenger($user);
        $conversation->setCreatedAt(new \DateTimeImmutable());
        $conversation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($conversation);

        $this->em->flush();

        // Notifier le conducteur
        $this->notificationService->notifyNewBooking($booking);

        return new JsonResponse([
            'message' => 'Réservation confirmée',
            'booking_id' => $booking->getId(),
            'conversation_id' => $conversation->getId(),
            'status' => 'paid',
        ]);
    }

    /**
     * Annulation par le passager
     */
    #[Route('/{id}/cancel', name: 'booking_cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $booking = $this->em->getRepository(Booking::class)->find($id);

        if (!$booking) {
            return new JsonResponse(['error' => 'Réservation introuvable'], 404);
        }

        if ($booking->getPassenger() !== $user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        if ($booking->getStatus() !== 'paid') {
            return new JsonResponse(['error' => 'Réservation non annulable'], 400);
        }

        $trip = $booking->getTrip();
        $departureAt = $trip->getDepartureAt();
        $now = new \DateTimeImmutable();
        $hoursUntilDeparture = ($departureAt->getTimestamp() - $now->getTimestamp()) / 3600;

        // Calcul du remboursement selon la politique
        $totalPaid = $booking->getTotalAmount();
        $driverAmount = $booking->getPricePerSeat() * $booking->getSeatsBooked();

        if ($hoursUntilDeparture > 48) {
            // > 48h : 100% remboursé
            $refundAmount = $totalPaid;
            $driverReceives = 0;
        } elseif ($hoursUntilDeparture > 24) {
            // 24h - 48h : 50% remboursé
            $refundAmount = (int) round($totalPaid / 2);
            $driverReceives = (int) round($driverAmount / 2);
        } else {
            // < 24h : 0% remboursé
            $refundAmount = 0;
            $driverReceives = $driverAmount;
        }

        try {
            // Rembourser le passager si montant > 0
            if ($refundAmount > 0 && $booking->getStripePaymentIntentId()) {
                $this->stripeService->refund($booking->getStripePaymentIntentId(), $refundAmount);
            }

            // Transférer au conducteur si montant > 0
            if ($driverReceives > 0 && $trip->getDriver()->getStripeAccountId()) {
                // On crée un transfert partiel si nécessaire
                $transfer = $this->stripeService->transferToDriver($booking, $driverReceives);
                $booking->setStripeTransferId($transfer->id);
            }

            // Mettre à jour la réservation
            $booking->setStatus('cancelled');
            $booking->setCancelledBy('passenger');
            $booking->setRefundedAmount($refundAmount);
            $booking->setRefundedAt(new \DateTimeImmutable());

            // Remettre les places disponibles
            $trip->setAvailableSeats($trip->getAvailableSeats() + $booking->getSeatsBooked());

            $this->em->flush();

            return new JsonResponse([
                'message' => 'Réservation annulée',
                'refunded_amount' => $refundAmount,
                'driver_receives' => $driverReceives,
                'hours_until_departure' => round($hoursUntilDeparture, 1),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur remboursement: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Annulation par le conducteur (trajet complet)
     */
    #[Route('/trip/{tripId}/cancel', name: 'booking_cancel_by_driver', methods: ['POST'])]
    public function cancelByDriver(int $tripId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $trip = $this->em->getRepository(Trip::class)->find($tripId);

        if (!$trip) {
            return new JsonResponse(['error' => 'Trajet introuvable'], 404);
        }

        if ($trip->getDriver() !== $user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        if ($trip->getStatus() === 'cancelled') {
            return new JsonResponse(['error' => 'Trajet déjà annulé'], 400);
        }

        $bookings = $trip->getBookings();
        $refundedCount = 0;

        foreach ($bookings as $booking) {
            if ($booking->getStatus() === 'paid') {
                try {
                    // Rembourser 100% au passager
                    if ($booking->getStripePaymentIntentId()) {
                        $this->stripeService->refund(
                            $booking->getStripePaymentIntentId(),
                            $booking->getTotalAmount()
                        );
                    }

                    $booking->setStatus('refunded');
                    $booking->setCancelledBy('driver');
                    $booking->setRefundedAmount($booking->getTotalAmount());
                    $booking->setRefundedAt(new \DateTimeImmutable());
                    $refundedCount++;

                } catch (\Exception $e) {
                    // Log l'erreur mais continue avec les autres
                    continue;
                }
            }
        }

        // Annuler le trajet
        $trip->setStatus('cancelled');
        $this->em->flush();

        return new JsonResponse([
            'message' => 'Trajet annulé',
            'bookings_refunded' => $refundedCount,
        ]);
    }
}