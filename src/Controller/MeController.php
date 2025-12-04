<?php

namespace App\Controller;

use App\Entity\User;
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
}