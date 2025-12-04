<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/users/{id}/profile', name: 'user_profile', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function profile(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 404);
        }

        // Nombre de trajets effectués en tant que conducteur
        $tripsAsDriver = $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Trip::class, 't')
            ->where('t.driver = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Nombre de trajets effectués en tant que passager
        $tripsAsPassenger = $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Booking::class, 'b')
            ->where('b.passenger = :user')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Avis récents (5 derniers)
        $recentReviews = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Review::class, 'r')
            ->where('r.target = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $reviewsData = array_map(function (Review $review) {
            return [
                'id' => $review->getId(),
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
                'author' => [
                    'id' => $review->getAuthor()->getId(),
                    'firstName' => $review->getAuthor()->getFirstName(),
                    'lastName' => substr($review->getAuthor()->getLastName(), 0, 1) . '.',
                    'avatar' => $review->getAuthor()->getAvatar(),
                    'defaultAvatar' => $review->getAuthor()->getDefaultAvatar(),
                ],
                'createdAt' => $review->getCreatedAt()->format('c'),
            ];
        }, $recentReviews);

        return new JsonResponse([
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => substr($user->getLastName(), 0, 1) . '.',
            'avatar' => $user->getAvatar(),
            'defaultAvatar' => $user->getDefaultAvatar(),
            'bio' => $user->getBio(),
            'averageRating' => $user->getAverageRating(),
            'reviewsCount' => $user->getReviewsCount(),
            'tripsAsDriver' => (int) $tripsAsDriver,
            'tripsAsPassenger' => (int) $tripsAsPassenger,
            'memberSince' => $user->getCreatedAt()->format('Y-m-d'),
            'recentReviews' => $reviewsData,
        ]);
    }
}