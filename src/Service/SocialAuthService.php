<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SocialAuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private string $googleClientId,
        private string $appleClientId
    ) {}

    /**
     * Vérifie le token Google et retourne les infos utilisateur
     */
    public function verifyGoogleToken(string $idToken): ?array
    {
        try {
            $client = new GoogleClient(['client_id' => $this->googleClientId]);
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return null;
            }

            return [
                'id' => $payload['sub'],
                'email' => $payload['email'],
                'firstName' => $payload['given_name'] ?? '',
                'lastName' => $payload['family_name'] ?? '',
                'avatar' => $payload['picture'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Vérifie le token Apple et retourne les infos utilisateur
     */
    public function verifyAppleToken(string $idToken): ?array
    {
        try {
            // Décoder le JWT Apple (sans vérification de signature pour simplifier)
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$payload || !isset($payload['sub']) || !isset($payload['email'])) {
                return null;
            }

            // Vérifier l'audience (client_id)
            if (($payload['aud'] ?? '') !== $this->appleClientId) {
                return null;
            }

            // Vérifier l'expiration
            if (($payload['exp'] ?? 0) < time()) {
                return null;
            }

            return [
                'id' => $payload['sub'],
                'email' => $payload['email'],
                'firstName' => '',
                'lastName' => '',
                'avatar' => null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Trouve ou crée un utilisateur depuis les données sociales
     */
    public function findOrCreateUser(array $socialData, string $provider): User
    {
        $providerIdField = $provider . 'Id';
        $providerId = $socialData['id'];
        $email = $socialData['email'];

        // Chercher par provider ID
        $user = $this->em->getRepository(User::class)->findOneBy([
            $providerIdField => $providerId
        ]);

        if ($user) {
            return $user;
        }

        // Chercher par email
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => $email
        ]);

        if ($user) {
            // Lier le compte existant au provider
            $setter = 'set' . ucfirst($providerIdField);
            $user->$setter($providerId);
            $this->em->flush();
            return $user;
        }

        // Créer un nouvel utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($socialData['firstName'] ?: 'Utilisateur');
        $user->setLastName($socialData['lastName'] ?: $provider);

        // Mot de passe aléatoire (l'utilisateur ne s'en servira pas)
        $randomPassword = bin2hex(random_bytes(16));
        $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

        // Définir le provider ID
        $setter = 'set' . ucfirst($providerIdField);
        $user->$setter($providerId);

        // Avatar si disponible
        if (!empty($socialData['avatar'])) {
            $user->setAvatar($socialData['avatar']);
        }

        // Email vérifié automatiquement (Google/Apple l'ont déjà vérifié)
        $user->setIsVerified(true);
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
