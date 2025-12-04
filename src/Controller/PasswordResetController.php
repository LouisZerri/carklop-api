<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmailService $emailService,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Email requis'], 400);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        // Toujours retourner succès (sécurité : ne pas révéler si l'email existe)
        if (!$user) {
            return new JsonResponse(['message' => 'Si cet email existe, vous recevrez un lien de réinitialisation.']);
        }

        // Générer le token
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $user->setResetPasswordToken($token);
        $user->setResetPasswordTokenExpiresAt($expiresAt);
        $this->em->flush();

        // Envoyer l'email
        try {
            $this->emailService->sendPasswordResetEmail($user);
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas la révéler
        }

        return new JsonResponse(['message' => 'Si cet email existe, vous recevrez un lien de réinitialisation.']);
    }

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $password = $data['password'] ?? null;

        if (!$token || !$password) {
            return new JsonResponse(['error' => 'Token et mot de passe requis'], 400);
        }

        if (strlen($password) < 6) {
            return new JsonResponse(['error' => 'Mot de passe trop court (minimum 6 caractères)'], 400);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['resetPasswordToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Token invalide'], 400);
        }

        // Vérifier l'expiration
        if ($user->getResetPasswordTokenExpiresAt() < new \DateTimeImmutable()) {
            return new JsonResponse(['error' => 'Token expiré'], 400);
        }

        // Mettre à jour le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setResetPasswordToken(null);
        $user->setResetPasswordTokenExpiresAt(null);
        $this->em->flush();

        return new JsonResponse(['message' => 'Mot de passe modifié avec succès']);
    }

    #[Route('/reset-password/verify/{token}', name: 'verify_reset_token', methods: ['GET'])]
    public function verifyResetToken(string $token): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['resetPasswordToken' => $token]);

        if (!$user) {
            return new JsonResponse(['valid' => false, 'error' => 'Token invalide'], 400);
        }

        if ($user->getResetPasswordTokenExpiresAt() < new \DateTimeImmutable()) {
            return new JsonResponse(['valid' => false, 'error' => 'Token expiré'], 400);
        }

        return new JsonResponse(['valid' => true]);
    }
}