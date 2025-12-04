<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class UploadController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('/upload/avatar', name: 'upload_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $file = $request->files->get('avatar');

        if (!$file) {
            return new JsonResponse(['error' => 'Aucun fichier envoyé'], 400);
        }

        // Vérifier le type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return new JsonResponse(['error' => 'Format invalide (JPG, PNG, WEBP)'], 400);
        }

        // Vérifier la taille (max 2Mo)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return new JsonResponse(['error' => 'Fichier trop volumineux (max 2Mo)'], 400);
        }

        // Supprimer l'ancien avatar si existe
        if ($user->getAvatar()) {
            $oldPath = $this->getParameter('kernel.project_dir') . '/public' . $user->getAvatar();
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Générer un nom unique
        $filename = $user->getId() . '-' . uniqid() . '.' . $file->guessExtension();

        // Déplacer le fichier
        $file->move(
            $this->getParameter('kernel.project_dir') . '/public/uploads/avatars',
            $filename
        );

        // Mettre à jour l'utilisateur
        $avatarUrl = '/uploads/avatars/' . $filename;
        $user->setAvatar($avatarUrl);
        $this->em->flush();

        return new JsonResponse([
            'message' => 'Avatar mis à jour',
            'avatar' => $avatarUrl,
        ]);
    }
}