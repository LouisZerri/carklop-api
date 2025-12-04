# Carklop API

Backend API pour Carklop, une plateforme de covoiturage transfrontalier.

## Stack Technique

- **Framework** : Symfony 7
- **API** : API Platform 4
- **Base de données** : MySQL 8
- **Authentification** : JWT (LexikJWTAuthenticationBundle)
- **Paiements** : Stripe Connect + Stripe Payments
- **Notifications** : Expo Push Notifications

## Installation

### Prérequis

- PHP 8.2+
- Composer
- MySQL 8
- OpenSSL (pour les clés JWT)

### Installation
```bash
# Cloner le projet
git clone <repo>
cd carklop-api

# Installer les dépendances
composer install

# Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec vos paramètres

# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Charger les fixtures (dev)
php bin/console doctrine:fixtures:load
```

### Variables d'environnement (.env.local)
```dotenv
DATABASE_URL="mysql://user:password@127.0.0.1:3306/carklop?serverVersion=8.0"
JWT_PASSPHRASE=your_passphrase
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
APP_URL=http://localhost:8000
MAILER_DSN=smtp://user:password@smtp.mailtrap.io:2525
```

## Lancer le serveur
```bash
# Avec Symfony CLI
symfony serve

# Ou avec PHP
php -S localhost:8000 -t public/
```

L'API est accessible sur `http://localhost:8000/api`

## Documentation API

Swagger UI disponible sur `http://localhost:8000/api`

## Architecture
```
src/
├── Controller/          # Controllers custom (Booking, Message, Stripe, etc.)
├── Entity/              # Entités Doctrine
├── Service/             # Services métier (Stripe, Email, Notification)
├── State/               # Processors API Platform
├── MessageHandler/      # Handlers Messenger (transferts auto)
├── Message/             # Messages Messenger
├── Scheduler/           # Scheduler pour tâches planifiées
├── Command/             # Commandes console
└── DataFixtures/        # Fixtures de test
```

## Entités

| Entité | Description |
|--------|-------------|
| User | Utilisateurs (conducteurs et passagers) |
| Trip | Trajets proposés par les conducteurs |
| Booking | Réservations des passagers |
| Conversation | Conversations liées aux réservations |
| Message | Messages dans les conversations |
| Review | Avis des passagers sur les conducteurs |
| Notification | Historique des notifications |
| DeviceToken | Tokens push Expo des appareils |

## Logique Métier

### Commission

- Prix conducteur : X €
- Commission CarKlop : 15%
- Total passager : X × 1.15

### Politique de remboursement (annulation passager)

| Délai avant départ | Remboursement passager | Part conducteur |
|--------------------|------------------------|-----------------|
| > 48h | 100% | 0% |
| 24h - 48h | 50% | 50% |
| < 24h | 0% | 100% |

### Annulation conducteur

- Passager remboursé à 100% quel que soit le délai

### Transfert au conducteur

- Automatique 2h après l'heure de retour prévue
- Scheduler toutes les 15 minutes

## Tests
```bash
# Créer la base de test
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Lancer tous les tests
php bin/phpunit tests/E2E/

# Lancer un fichier de test
php bin/phpunit tests/E2E/UserTest.php

# Lancer un test spécifique
php bin/phpunit --filter testInscription
```

### Couverture des tests (97 tests)

- UserTest : 30 tests (inscription, login, profil, avatar, vérification email)
- TripTest : 7 tests (CRUD trajets, filtres)
- BookingTest : 8 tests (réservation, confirmation, paiement)
- CancellationTest : 8 tests (annulations, remboursements)
- MessageTest : 9 tests (conversations, messages)
- ReviewTest : 7 tests (avis, notation)
- StripeConnectTest : 6 tests (onboarding conducteur)
- StripeWebhookTest : 5 tests (webhooks paiement)
- NotificationTest : 11 tests (tokens push, notifications)
- TransferTest : 6 tests (transferts automatiques)

## Commandes utiles
```bash
# Vider le cache
php bin/console cache:clear

# Voir les routes
php bin/console debug:router

# Lancer le scheduler (production)
php bin/console messenger:consume scheduler_default
```

## Fixtures de test

| Email | Mot de passe | Rôle |
|-------|--------------|------|
| admin@carklop.fr | password123 | Admin |
| marie@test.fr | password123 | Conducteur (Stripe) |
| thomas@test.fr | password123 | Conducteur (Stripe) |
| sophie@test.fr | password123 | Conducteur (Stripe) |
| jean@test.fr | password123 | Passager |
| claire@test.fr | password123 | Passager |
| pierre@test.fr | password123 | Passager |

## Production

### Webhook Stripe

Configurer le webhook Stripe pour pointer vers :
```
https://votre-domaine.com/api/webhook/stripe
```

Events à activer :
- `payment_intent.succeeded`
- `payment_intent.payment_failed`

### Scheduler

Lancer le worker Messenger pour les transferts automatiques :
```bash
php bin/console messenger:consume scheduler_default
```