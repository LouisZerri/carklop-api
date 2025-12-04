<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/me')]
class MeController extends AbstractController
{
    public function __construct(
        private SerializerInterface $serializer
    ) {}

    /**
     * Profil de l'utilisateur connecté
     */
    #[Route('', name: 'me_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $data = $this->serializer->serialize($user, 'json', ['groups' => 'user:read']);

        return new JsonResponse(json_decode($data), 200);
    }

    /**
     * Trajets où je suis conducteur
     */
    #[Route('/trips', name: 'me_trips', methods: ['GET'])]
    public function myTrips(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $trips = $user->getTrips();
        $data = $this->serializer->serialize($trips, 'json', ['groups' => 'trip:read']);

        return new JsonResponse(json_decode($data), 200);
    }

    /**
     * Mes réservations en tant que passager
     */
    #[Route('/bookings', name: 'me_bookings', methods: ['GET'])]
    public function myBookings(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $bookings = $user->getBookings();
        $data = $this->serializer->serialize($bookings, 'json', ['groups' => 'booking:read']);

        return new JsonResponse(json_decode($data), 200);
    }

    #[Route('/stats', name: 'me_stats', methods: ['GET'])]
    public function stats(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        // Trajets complétés en tant que passager
        $completedBookings = $em->createQueryBuilder()
            ->select('b')
            ->from(\App\Entity\Booking::class, 'b')
            ->where('b.passenger = :user')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['completed', 'paid'])
            ->getQuery()
            ->getResult();

        $totalTrips = count($completedBookings);
        $totalSavings = 0;

        foreach ($completedBookings as $booking) {
            $totalSavings += $booking->getEstimatedSavings() ?? 0;
        }

        // Trajets en tant que conducteur
        $tripsAsDriver = $em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(\App\Entity\Trip::class, 't')
            ->where('t.driver = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'totalSavings' => $totalSavings,
            'tripsAsPassenger' => $totalTrips,
            'tripsAsDriver' => (int) $tripsAsDriver,
            'message' => $totalSavings > 0 
                ? "{$totalSavings}€ économisés sur {$totalTrips} trajets"
                : "Aucune économie pour le moment",
        ]);
    }
}