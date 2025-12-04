<?php

namespace App\Controller;

use App\Service\SocialAuthService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class SocialAuthController extends AbstractController
{
    public function __construct(
        private SocialAuthService $socialAuthService,
        private JWTTokenManagerInterface $jwtManager
    ) {}

    #[Route('/google', name: 'auth_google', methods: ['POST'])]
    public function google(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['id_token'] ?? null;

        if (!$idToken) {
            return new JsonResponse(['error' => 'id_token requis'], 400);
        }

        $socialData = $this->socialAuthService->verifyGoogleToken($idToken);

        if (!$socialData) {
            return new JsonResponse(['error' => 'Token Google invalide'], 401);
        }

        $user = $this->socialAuthService->findOrCreateUser($socialData, 'google');
        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }

    #[Route('/apple', name: 'auth_apple', methods: ['POST'])]
    public function apple(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['id_token'] ?? null;
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;

        if (!$idToken) {
            return new JsonResponse(['error' => 'id_token requis'], 400);
        }

        $socialData = $this->socialAuthService->verifyAppleToken($idToken);

        if (!$socialData) {
            return new JsonResponse(['error' => 'Token Apple invalide'], 401);
        }

        // Apple envoie le nom uniquement à la première connexion
        if ($firstName) {
            $socialData['firstName'] = $firstName;
        }
        if ($lastName) {
            $socialData['lastName'] = $lastName;
        }

        $user = $this->socialAuthService->findOrCreateUser($socialData, 'apple');
        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }
}