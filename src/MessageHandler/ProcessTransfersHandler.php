<?php

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\ProcessTransfersMessage;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessTransfersHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private StripeService $stripeService,
        private LoggerInterface $logger
    ) {}

    public function __invoke(ProcessTransfersMessage $message): void
    {
        $twoHoursAgo = new \DateTimeImmutable('-2 hours');

        $bookings = $this->em->createQueryBuilder()
            ->select('b')
            ->from(Booking::class, 'b')
            ->join('b.trip', 't')
            ->where('b.status = :status')
            ->andWhere('t.returnAt <= :twoHoursAgo')
            ->andWhere('b.stripeTransferId IS NULL')
            ->setParameter('status', 'paid')
            ->setParameter('twoHoursAgo', $twoHoursAgo)
            ->getQuery()
            ->getResult();

        foreach ($bookings as $booking) {
            $driver = $booking->getTrip()->getDriver();

            if (!$driver->getStripeAccountId()) {
                $this->logger->warning(sprintf('Booking #%d : Conducteur sans compte Stripe', $booking->getId()));
                continue;
            }

            try {
                $transfer = $this->stripeService->transferToDriver($booking);
                $booking->setStripeTransferId($transfer->id);
                $booking->setStatus('completed');
                $this->em->flush();

                $this->logger->info(sprintf('Booking #%d : Transfert effectué', $booking->getId()));

            } catch (\Exception $e) {
                $this->logger->error(sprintf('Booking #%d : Erreur - %s', $booking->getId(), $e->getMessage()));
            }
        }
    }
}