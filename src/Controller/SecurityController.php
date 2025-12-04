<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Ce code ne sera jamais exécuté car le firewall intercepte avant
        $user = $this->getUser();
        
        return new JsonResponse([
            'email' => $user->getUserIdentifier(),
        ]);
    }
}