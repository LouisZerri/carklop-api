<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Trip;
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
        private SerializerInterface $serializer,
        private EntityManagerInterface $em
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
     * Trajets où je suis conducteur (liste)
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
     * Détail d'un trajet où je suis conducteur
     */
    #[Route('/trips/{id}', name: 'api_me_trip_detail', methods: ['GET'])]
    public function myTripDetail(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $trip = $this->em->getRepository(Trip::class)->find($id);
        if (!$trip || $trip->getDriver() !== $user) {
            return new JsonResponse(['error' => 'Trajet non trouvé'], 404);
        }

        $bookings = $this->em->getRepository(Booking::class)->findBy(['trip' => $trip]);

        return new JsonResponse([
            'id' => $trip->getId(),
            'departureCity' => $trip->getDepartureCity(),
            'departureCountry' => $trip->getDepartureCountry(),
            'departureAddress' => $trip->getDepartureAddress(),
            'destinationCity' => $trip->getDestinationCity(),
            'destinationCountry' => $trip->getDestinationCountry(),
            'destinationAddress' => $trip->getDestinationAddress(),
            'departureAt' => $trip->getDepartureAt()?->format('c'),
            'returnAt' => $trip->getReturnAt()?->format('c'),
            'pricePerSeat' => $trip->getPricePerSeat(),
            'availableSeats' => $trip->getAvailableSeats(),
            'status' => $trip->getStatus(),
            'description' => $trip->getDescription(),
            'bookings' => array_map(function (Booking $booking) {
                return [
                    'id' => $booking->getId(),
                    'seatsBooked' => $booking->getSeatsBooked(),
                    'totalAmount' => $booking->getTotalAmount(),
                    'status' => $booking->getStatus(),
                    'createdAt' => $booking->getCreatedAt()?->format('c'),
                    'conversationId' => $booking->getConversation()?->getId(),
                    'passenger' => [
                        'id' => $booking->getPassenger()->getId(),
                        'firstName' => $booking->getPassenger()->getFirstName(),
                        'lastName' => substr($booking->getPassenger()->getLastName(), 0, 1) . '.',
                        'avatar' => $booking->getPassenger()->getAvatar(),
                        'defaultAvatar' => $booking->getPassenger()->getDefaultAvatar(),
                        'averageRating' => $booking->getPassenger()->getAverageRating(),
                        'reviewsCount' => $booking->getPassenger()->getReviewsCount(),
                    ],
                ];
            }, $bookings),
        ]);
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

        $bookings = [];
        foreach ($user->getBookings() as $booking) {
            $trip = $booking->getTrip();
            $driver = $trip->getDriver();

            $bookings[] = [
                'id' => $booking->getId(),
                'seatsBooked' => $booking->getSeatsBooked(),
                'totalAmount' => $booking->getTotalAmount(),
                'status' => $booking->getStatus(),
                'estimatedSavings' => $booking->getEstimatedSavings(),
                'createdAt' => $booking->getCreatedAt()?->format('c'),
                'trip' => [
                    'id' => $trip->getId(),
                    'departureCity' => $trip->getDepartureCity(),
                    'departureCountry' => $trip->getDepartureCountry(),
                    'destinationCity' => $trip->getDestinationCity(),
                    'destinationCountry' => $trip->getDestinationCountry(),
                    'departureAt' => $trip->getDepartureAt()?->format('c'),
                    'returnAt' => $trip->getReturnAt()?->format('c'),
                ],
                'driver' => [
                    'id' => $driver->getId(),
                    'firstName' => $driver->getFirstName(),
                    'lastName' => substr($driver->getLastName(), 0, 1) . '.',
                    'avatar' => $driver->getAvatar(),
                    'defaultAvatar' => $driver->getDefaultAvatar(),
                    'averageRating' => $driver->getAverageRating(),
                    'reviewsCount' => $driver->getReviewsCount(),
                ],
            ];
        }

        return new JsonResponse($bookings);
    }

    /**
     * Statistiques sur mes voyages et économies réalisées
     */
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
            ->join('b.trip', 't')
            ->where('b.passenger = :user')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['completed', 'paid'])
            ->orderBy('t.departureAt', 'DESC')
            ->getQuery()
            ->getResult();

        $totalTrips = count($completedBookings);
        $totalSavings = 0;
        $monthlyStats = [];

        foreach ($completedBookings as $booking) {
            $savings = $booking->getEstimatedSavings() ?? 0;
            $totalSavings += $savings;

            // Grouper par mois
            $tripDate = $booking->getTrip()->getDepartureAt();
            $monthKey = $tripDate->format('Y-m');
            $monthLabel = $tripDate->format('F Y'); // "December 2024"

            if (!isset($monthlyStats[$monthKey])) {
                $monthlyStats[$monthKey] = [
                    'month' => $monthKey,
                    'label' => $monthLabel,
                    'savings' => 0,
                    'trips' => 0,
                ];
            }

            $monthlyStats[$monthKey]['savings'] += $savings;
            $monthlyStats[$monthKey]['trips'] += 1;
        }

        // Trier par mois décroissant et prendre les 6 derniers mois
        krsort($monthlyStats);
        $monthlyStats = array_values(array_slice($monthlyStats, 0, 6));

        // Trajets en tant que conducteur
        $tripsAsDriver = $em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Trip::class, 't')
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
            'monthlyStats' => $monthlyStats,
            'message' => $totalSavings > 0
                ? "{$totalSavings}€ économisés sur {$totalTrips} trajets"
                : "Aucune économie pour le moment",
        ]);
    }
}
