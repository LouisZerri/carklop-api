<?php

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Message\ProcessTransfersMessage;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessTransfersHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private StripeService $stripeService
    ) {}

    public function __invoke(ProcessTransfersMessage $message): void
    {
        $now = new \DateTimeImmutable();
        $autoCompleteDelay = new \DateInterval('PT48H'); // 48 heures

        // Trouver les bookings "paid" dont le trajet est terminé depuis plus de 48h
        $bookings = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Booking::class, 'b')
            ->join('b.trip', 't')
            ->where('b.status = :status')
            ->andWhere('t.returnAt < :autoCompleteTime')
            ->andWhere('b.stripeTransferId IS NULL')
            ->setParameter('status', 'paid')
            ->setParameter('autoCompleteTime', $now->sub($autoCompleteDelay))
            ->getQuery()
            ->getResult();

        foreach ($bookings as $booking) {
            $trip = $booking->getTrip();
            $driver = $trip->getDriver();

            // Marquer comme complété
            $booking->setStatus('completed');

            // Transférer au conducteur
            if ($driver->getStripeAccountId()) {
                try {
                    $amount = $booking->getPricePerSeat() * $booking->getSeatsBooked();
                    $transfer = $this->stripeService->transferToDriver($booking, $amount);
                    $booking->setStripeTransferId($transfer->id);
                } catch (\Exception $e) {
                    // Log l'erreur mais continue
                    continue;
                }
            }
        }

        // Mettre à jour les trajets dont tous les bookings sont terminés
        $trips = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Trip::class, 't')
            ->where('t.status = :status')
            ->andWhere('t.returnAt < :now')
            ->setParameter('status', 'published')
            ->setParameter('now', $now->sub($autoCompleteDelay))
            ->getQuery()
            ->getResult();

        foreach ($trips as $trip) {
            $allCompleted = true;
            foreach ($trip->getBookings() as $booking) {
                if (in_array($booking->getStatus(), ['pending', 'paid'])) {
                    $allCompleted = false;
                    break;
                }
            }

            if ($allCompleted) {
                $trip->setStatus('completed');
            }
        }

        $this->em->flush();
    }
}