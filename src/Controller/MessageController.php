<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/messages')]
class MessageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService
    ) {}

    /**
     * Mes conversations
     */
    #[Route('/conversations', name: 'message_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $conversations = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Conversation::class, 'c')
            ->where('c.driver = :user OR c.passenger = :user')
            ->setParameter('user', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($conversations as $conv) {
            $otherUser = $conv->getDriver() === $user ? $conv->getPassenger() : $conv->getDriver();
            
            // Dernier message
            $lastMessage = $this->em->getRepository(Message::class)->findOneBy(
                ['conversation' => $conv],
                ['createdAt' => 'DESC']
            );

            // Nombre de non lus
            $unreadCount = $this->em->createQueryBuilder()
                ->select('COUNT(m.id)')
                ->from(Message::class, 'm')
                ->where('m.conversation = :conv')
                ->andWhere('m.sender != :user')
                ->andWhere('m.isRead = false')
                ->setParameter('conv', $conv)
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            $data[] = [
                'id' => $conv->getId(),
                'booking_id' => $conv->getBooking()->getId(),
                'trip' => [
                    'id' => $conv->getBooking()->getTrip()->getId(),
                    'departureCity' => $conv->getBooking()->getTrip()->getDepartureCity(),
                    'destinationCity' => $conv->getBooking()->getTrip()->getDestinationCity(),
                ],
                'otherUser' => [
                    'id' => $otherUser->getId(),
                    'firstName' => $otherUser->getFirstName(),
                    'lastName' => $otherUser->getLastName(),
                    'avatar' => $otherUser->getAvatar(),
                    'defaultAvatar' => $otherUser->getDefaultAvatar(),
                ],
                'lastMessage' => $lastMessage ? [
                    'content' => $lastMessage->getContent(),
                    'createdAt' => $lastMessage->getCreatedAt()->format('c'),
                    'isMe' => $lastMessage->getSender() === $user,
                ] : null,
                'unreadCount' => (int) $unreadCount,
                'updatedAt' => $conv->getUpdatedAt()->format('c'),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Messages d'une conversation
     */
    #[Route('/conversations/{id}', name: 'message_list', methods: ['GET'])]
    public function list(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $conversation = $this->em->getRepository(Conversation::class)->find($id);

        if (!$conversation) {
            return new JsonResponse(['error' => 'Conversation introuvable'], 404);
        }

        // Vérifier que l'utilisateur fait partie de la conversation
        if ($conversation->getDriver() !== $user && $conversation->getPassenger() !== $user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $messages = $this->em->getRepository(Message::class)->findBy(
            ['conversation' => $conversation],
            ['createdAt' => 'ASC']
        );

        // Marquer les messages reçus comme lus
        foreach ($messages as $message) {
            if ($message->getSender() !== $user && !$message->isRead()) {
                $message->setIsRead(true);
            }
        }
        $this->em->flush();

        $data = [];
        foreach ($messages as $message) {
            $data[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'isMe' => $message->getSender() === $user,
                'sender' => [
                    'id' => $message->getSender()->getId(),
                    'firstName' => $message->getSender()->getFirstName(),
                    'avatar' => $message->getSender()->getAvatar(),
                    'defaultAvatar' => $message->getSender()->getDefaultAvatar(),
                ],
                'isRead' => $message->isRead(),
                'createdAt' => $message->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Envoyer un message
     */
    #[Route('/conversations/{id}/send', name: 'message_send', methods: ['POST'])]
    public function send(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $conversation = $this->em->getRepository(Conversation::class)->find($id);

        if (!$conversation) {
            return new JsonResponse(['error' => 'Conversation introuvable'], 404);
        }

        // Vérifier que l'utilisateur fait partie de la conversation
        if ($conversation->getDriver() !== $user && $conversation->getPassenger() !== $user) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return new JsonResponse(['error' => 'Message vide'], 400);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setContent(trim($data['content']));
        $message->setIsRead(false);
        $message->setCreatedAt(new \DateTimeImmutable());

        $conversation->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($message);
        $this->em->flush();

        // Notifier l'autre utilisateur
        $otherUser = $conversation->getDriver() === $user 
            ? $conversation->getPassenger() 
            : $conversation->getDriver();

        $this->notificationService->send(
            $otherUser,
            'Nouveau message',
            $user->getFirstName() . ': ' . mb_substr($message->getContent(), 0, 50),
            'message_new',
            ['conversation_id' => $conversation->getId()]
        );

        return new JsonResponse([
            'message_id' => $message->getId(),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format('c'),
        ], 201);
    }

    /**
     * Démarrer une conversation (appelé automatiquement après réservation)
     */
    #[Route('/start/{bookingId}', name: 'message_start', methods: ['POST'])]
    public function start(int $bookingId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $booking = $this->em->getRepository(Booking::class)->find($bookingId);

        if (!$booking) {
            return new JsonResponse(['error' => 'Réservation introuvable'], 404);
        }

        // Vérifier que c'est bien le passager ou le conducteur
        $isPassenger = $booking->getPassenger() === $user;
        $isDriver = $booking->getTrip()->getDriver() === $user;

        if (!$isPassenger && !$isDriver) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        // Vérifier si une conversation existe déjà
        $existing = $this->em->getRepository(Conversation::class)->findOneBy(['booking' => $booking]);

        if ($existing) {
            return new JsonResponse([
                'conversation_id' => $existing->getId(),
                'message' => 'Conversation existante',
            ]);
        }

        // Créer la conversation
        $conversation = new Conversation();
        $conversation->setBooking($booking);
        $conversation->setDriver($booking->getTrip()->getDriver());
        $conversation->setPassenger($booking->getPassenger());
        $conversation->setCreatedAt(new \DateTimeImmutable());
        $conversation->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($conversation);
        $this->em->flush();

        return new JsonResponse([
            'conversation_id' => $conversation->getId(),
            'message' => 'Conversation créée',
        ], 201);
    }
}