<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient
    ) {}

    /**
     * Envoie une notification push et l'enregistre en base
     */
    public function send(User $user, string $title, string $body, string $type, ?array $data = null): void
    {
        // Enregistrer en base
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setBody($body);
        $notification->setType($type);
        $notification->setData($data);
        $notification->setIsRead(false);
        $notification->setCreatedAt(new \DateTimeImmutable());
        
        $this->em->persist($notification);
        $this->em->flush();

        // Envoyer le push
        $this->sendPush($user, $title, $body, $data);
    }

    /**
     * Envoie le push via Expo
     */
    private function sendPush(User $user, string $title, string $body, ?array $data = null): void
    {
        $tokens = $user->getDeviceTokens();

        if ($tokens->isEmpty()) {
            return;
        }

        $messages = [];
        foreach ($tokens as $deviceToken) {
            $messages[] = [
                'to' => $deviceToken->getToken(),
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data ?? [],
            ];
        }

        try {
            $this->httpClient->request('POST', self::EXPO_PUSH_URL, [
                'json' => $messages,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas
        }
    }

    /**
     * Notifications de réservation
     */
    public function notifyNewBooking(\App\Entity\Booking $booking): void
    {
        $driver = $booking->getTrip()->getDriver();
        $passenger = $booking->getPassenger();

        $this->send(
            $driver,
            'Nouvelle réservation !',
            $passenger->getFirstName() . ' a réservé ' . $booking->getSeatsBooked() . ' place(s)',
            'booking_new',
            ['booking_id' => $booking->getId()]
        );
    }

    public function notifyBookingCancelled(\App\Entity\Booking $booking, string $cancelledBy): void
    {
        if ($cancelledBy === 'passenger') {
            $driver = $booking->getTrip()->getDriver();
            $this->send(
                $driver,
                'Réservation annulée',
                $booking->getPassenger()->getFirstName() . ' a annulé sa réservation',
                'booking_cancelled',
                ['booking_id' => $booking->getId()]
            );
        } else {
            $passenger = $booking->getPassenger();
            $this->send(
                $passenger,
                'Trajet annulé',
                'Le conducteur a annulé le trajet. Vous serez remboursé.',
                'trip_cancelled',
                ['booking_id' => $booking->getId()]
            );
        }
    }

    public function notifyNewReview(\App\Entity\Review $review): void
    {
        $this->send(
            $review->getTarget(),
            'Nouvel avis !',
            $review->getAuthor()->getFirstName() . ' vous a donné ' . $review->getRating() . ' étoiles',
            'review_received',
            ['review_id' => $review->getId()]
        );
    }
}