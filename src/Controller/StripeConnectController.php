<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api/stripe')]
class StripeConnectController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EntityManagerInterface $em
    ) {}

    /**
     * Crée un compte Connect et retourne le lien d'onboarding
     */
    #[Route('/connect/onboarding', name: 'stripe_connect_onboarding', methods: ['POST'])]
    public function createOnboarding(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $returnUrl = $data['return_url'] ?? null;
        $refreshUrl = $data['refresh_url'] ?? null;

        if (!$returnUrl || !$refreshUrl) {
            return new JsonResponse(['error' => 'return_url et refresh_url requis'], 400);
        }

        // Créer le compte Connect si pas encore fait
        if (!$user->getStripeAccountId()) {
            $accountId = $this->stripeService->createConnectAccount($user);
            $user->setStripeAccountId($accountId);
            $this->em->flush();
        }

        $onboardingUrl = $this->stripeService->createOnboardingLink(
            $user->getStripeAccountId(),
            $returnUrl,
            $refreshUrl
        );

        return new JsonResponse([
            'onboarding_url' => $onboardingUrl,
            'account_id' => $user->getStripeAccountId(),
        ]);
    }

    /**
     * Vérifie si le compte Connect est actif
     */
    #[Route('/connect/status', name: 'stripe_connect_status', methods: ['GET'])]
    public function checkStatus(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        if (!$user->getStripeAccountId()) {
            return new JsonResponse([
                'account_active' => false,
                'account_id' => null,
            ]);
        }

        $isActive = $this->stripeService->isAccountActive($user->getStripeAccountId());

        return new JsonResponse([
            'account_active' => $isActive,
            'account_id' => $user->getStripeAccountId(),
        ]);
    }
}