<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Trip;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TripStateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Trip && $data->getId() === null) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            
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

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}