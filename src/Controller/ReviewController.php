<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reviews')]
class ReviewController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'review_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['booking_id']) || !isset($data['rating'])) {
            return new JsonResponse(['error' => 'booking_id et rating requis'], 400);
        }

        $booking = $this->em->getRepository(Booking::class)->find($data['booking_id']);

        if (!$booking) {
            return new JsonResponse(['error' => 'Réservation introuvable'], 404);
        }

        // Vérifier que c'est bien le passager de cette réservation
        if ($booking->getPassenger() !== $user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        // Vérifier que le trajet est terminé
        if ($booking->getStatus() !== 'completed') {
            return new JsonResponse(['error' => 'Le trajet doit être terminé pour laisser un avis'], 400);
        }

        // Vérifier qu'un avis n'existe pas déjà
        $existingReview = $this->em->getRepository(Review::class)->findOneBy([
            'booking' => $booking,
            'author' => $user,
        ]);

        if ($existingReview) {
            return new JsonResponse(['error' => 'Vous avez déjà laissé un avis'], 400);
        }

        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) {
            return new JsonResponse(['error' => 'Note entre 1 et 5'], 400);
        }

        $review = new Review();
        $review->setBooking($booking);
        $review->setAuthor($user);
        $review->setTarget($booking->getTrip()->getDriver());
        $review->setRating($rating);
        $review->setComment($data['comment'] ?? null);
        $review->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($review);
        $this->em->flush();

        return new JsonResponse([
            'message' => 'Avis publié',
            'review_id' => $review->getId(),
        ], 201);
    }

    #[Route('/user/{id}', name: 'review_by_user', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function byUser(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 404);
        }

        $reviews = $this->em->getRepository(Review::class)->findBy(
            ['target' => $user],
            ['createdAt' => 'DESC']
        );

        $data = [];
        foreach ($reviews as $review) {
            $data[] = [
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
        }

        return new JsonResponse($data);
    }
}