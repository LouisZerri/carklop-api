<?php

namespace App\Controller;

use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $stripeWebhookSecret,
        private string $appEnv = 'prod'
    ) {}

    #[Route('/api/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        
        // En environnement de test, on parse directement le JSON
        if ($this->appEnv === 'test') {
            $data = json_decode($payload, true);
            if (!$data || !isset($data['type'])) {
                return new JsonResponse(['error' => 'Payload invalide'], 400);
            }
            $eventType = $data['type'];
            $eventData = (object) $data['data']['object'];
        } else {
            // En production, vérifier la signature Stripe
            $sigHeader = $request->headers->get('Stripe-Signature');

            try {
                $event = Webhook::constructEvent(
                    $payload,
                    $sigHeader,
                    $this->stripeWebhookSecret
                );
                $eventType = $event->type;
                $eventData = $event->data->object;
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Signature invalide'], 400);
            }
        }

        switch ($eventType) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($eventData);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($eventData);
                break;
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function handlePaymentSuccess(object $paymentIntent): void
    {
        $paymentIntentId = $paymentIntent->id ?? $paymentIntent->{'id'} ?? null;
        
        $booking = $this->em->getRepository(Booking::class)
            ->findOneBy(['stripePaymentIntentId' => $paymentIntentId]);

        if (!$booking || $booking->getStatus() !== 'pending') {
            return;
        }

        $booking->setStatus('paid');
        $booking->setPaidAt(new \DateTimeImmutable());

        // Réduire les places disponibles
        $trip = $booking->getTrip();
        $trip->setAvailableSeats($trip->getAvailableSeats() - $booking->getSeatsBooked());

        $this->em->flush();
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $paymentIntentId = $paymentIntent->id ?? $paymentIntent->{'id'} ?? null;
        
        $booking = $this->em->getRepository(Booking::class)
            ->findOneBy(['stripePaymentIntentId' => $paymentIntentId]);

        if (!$booking) {
            return;
        }

        $booking->setStatus('failed');
        $this->em->flush();
    }
}