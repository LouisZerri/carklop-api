<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\EmailService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserPasswordHasher implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailService $emailService
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User) {
            $isNew = $data->getId() === null;

            // Hasher le mot de passe seulement si plainPassword est fourni
            if ($data->getPlainPassword()) {
                $hashedPassword = $this->passwordHasher->hashPassword($data, $data->getPlainPassword());
                $data->setPassword($hashedPassword);
                $data->setPlainPassword(null);
            }

            // Nouvelle inscription : générer token et envoyer email
            if ($isNew) {
                $token = bin2hex(random_bytes(32));
                $data->setEmailVerificationToken($token);
                $data->setIsVerified(false);
            }

            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            // Envoyer l'email après persistence (seulement pour les nouveaux)
            if ($isNew && $data->getEmailVerificationToken()) {
                try {
                    $this->emailService->sendVerificationEmail($data);
                } catch (\Exception $e) {
                    // Log l'erreur mais ne bloque pas l'inscription
                }
            }

            return $result;
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}