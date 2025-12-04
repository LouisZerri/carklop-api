<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmailService $emailService
    ) {}

    #[Route('/api/verify-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function verify(string $token): Response
    {
        $user = $this->em->getRepository(User::class)->findOneBy([
            'emailVerificationToken' => $token
        ]);

        if (!$user) {
            return new Response(
                '<html><body><h1>Lien invalide ou expiré</h1><p>Ce lien de vérification n\'est plus valide.</p></body></html>',
                404,
                ['Content-Type' => 'text/html']
            );
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null);
        $this->em->flush();

        return new Response(
            '<html><body><h1>Email vérifié !</h1><p>Votre compte CarKlop est maintenant actif. Vous pouvez retourner sur l\'application.</p></body></html>',
            200,
            ['Content-Type' => 'text/html']
        );
    }

    #[Route('/api/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resend(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['error' => 'Email déjà vérifié'], 400);
        }

        try {
            // Générer un nouveau token
            $token = bin2hex(random_bytes(32));
            $user->setEmailVerificationToken($token);
            $this->em->flush();

            $this->emailService->sendVerificationEmail($user);
            return new JsonResponse(['message' => 'Email de vérification envoyé']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'envoi'], 500);
        }
    }
}