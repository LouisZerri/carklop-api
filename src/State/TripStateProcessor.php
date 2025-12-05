<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Trip;
use App\Entity\User;
use App\Service\StripeService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TripStateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private StripeService $stripeService
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Trip) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            
            $isNew = $data->getId() === null;
            
            if ($isNew) {
                // Vérifier que l'utilisateur est connecté
                if (!$user) {
                    throw new AccessDeniedHttpException('Vous devez être connecté pour créer un trajet.');
                }
                
                // Vérifier que l'email est validé
                if (!$user->isVerified()) {
                    throw new AccessDeniedHttpException('Vous devez vérifier votre email avant de publier un trajet.');
                }
                
                $data->setDriver($user);
                $data->setCreatedAt(new \DateTimeImmutable());
                
                if (!$data->getStatus()) {
                    $data->setStatus('draft');
                }
            }
            
            // Vérifier le compte Stripe si on publie
            if ($data->getStatus() === 'published') {
                $driver = $data->getDriver() ?? $user;
                
                if (!$driver->getStripeAccountId()) {
                    throw new AccessDeniedHttpException('Vous devez configurer votre compte de paiement avant de publier un trajet.');
                }
                
                // Vérifier que le compte est actif
                if (!$this->stripeService->isAccountActive($driver->getStripeAccountId())) {
                    throw new AccessDeniedHttpException('Votre compte de paiement n\'est pas encore actif. Veuillez finaliser votre inscription Stripe.');
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}