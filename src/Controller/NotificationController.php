<?php

namespace App\Controller;

use App\Entity\DeviceToken;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Enregistrer un token push
     */
    #[Route('/register-token', name: 'notification_register_token', methods: ['POST'])]
    public function registerToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['token']) || !isset($data['platform'])) {
            return new JsonResponse(['error' => 'token et platform requis'], 400);
        }

        $token = $data['token'];
        $platform = $data['platform'];

        if (!in_array($platform, ['ios', 'android'])) {
            return new JsonResponse(['error' => 'Platform invalide (ios ou android)'], 400);
        }

        // Vérifier si le token existe déjà
        $existing = $this->em->getRepository(DeviceToken::class)->findOneBy([
            'user' => $user,
            'token' => $token,
        ]);

        if ($existing) {
            return new JsonResponse(['message' => 'Token déjà enregistré']);
        }

        $deviceToken = new DeviceToken();
        $deviceToken->setUser($user);
        $deviceToken->setToken($token);
        $deviceToken->setPlatform($platform);
        $deviceToken->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($deviceToken);
        $this->em->flush();

        return new JsonResponse(['message' => 'Token enregistré'], 201);
    }

    /**
     * Mes notifications
     */
    #[Route('', name: 'notification_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $notifications = $this->em->getRepository(Notification::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            50
        );

        $data = [];
        foreach ($notifications as $notif) {
            $data[] = [
                'id' => $notif->getId(),
                'title' => $notif->getTitle(),
                'body' => $notif->getBody(),
                'type' => $notif->getType(),
                'data' => $notif->getData(),
                'isRead' => $notif->isRead(),
                'createdAt' => $notif->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Marquer comme lue
     */
    #[Route('/{id}/read', name: 'notification_read', methods: ['POST'])]
    public function markAsRead(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $notification = $this->em->getRepository(Notification::class)->find($id);

        if (!$notification || $notification->getUser() !== $user) {
            return new JsonResponse(['error' => 'Notification introuvable'], 404);
        }

        $notification->setIsRead(true);
        $this->em->flush();

        return new JsonResponse(['message' => 'Notification lue']);
    }

    /**
     * Marquer toutes comme lues
     */
    #[Route('/read-all', name: 'notification_read_all', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $this->em->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.isRead', true)
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        return new JsonResponse(['message' => 'Toutes les notifications lues']);
    }
}