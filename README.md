# CarKlop API

Backend API pour CarKlop, une plateforme de covoiturage transfrontalier.

## Stack Technique

- **Framework** : Symfony 7
- **API** : API Platform 4
- **Base de données** : MySQL 8
- **Authentification** : JWT + Google + Apple Sign In
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
git clone https://github.com/LouisZerri/carklop-api
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
GOOGLE_CLIENT_ID=your_google_client_id
APPLE_CLIENT_ID=your_apple_client_id
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
├── Controller/          # Controllers custom (Booking, Message, Stripe, SocialAuth, etc.)
├── Entity/              # Entités Doctrine
├── Service/             # Services métier (Stripe, Email, Notification, SocialAuth)
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
| SavingsEstimate | Estimations d'économies par pays |

## Authentification

### JWT (email/password)
- `POST /api/login` : Connexion classique
- `POST /api/users` : Inscription

### OAuth (réseaux sociaux)
- `POST /api/auth/google` : Connexion Google
- `POST /api/auth/apple` : Connexion Apple

Les connexions sociales créent automatiquement un compte si l'email n'existe pas, ou lient le compte social à un compte existant.

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

### Confirmation de fin de trajet

| Action | Qui | Délai | Résultat |
|--------|-----|-------|----------|
| Confirmer le retour | Passager | Après returnAt | Transfert immédiat au conducteur |
| Auto-completion | Système (cron) | 48h après returnAt | Transfert automatique |

Le passager peut laisser un avis uniquement après avoir confirmé la fin du trajet.

### Transfert au conducteur

- Manuel : déclenché quand le passager confirme la fin du trajet
- Automatique : 48h après l'heure de retour si le passager n'a pas confirmé
- Scheduler toutes les 15 minutes

### Réservation unique

Un passager ne peut avoir qu'une seule réservation active à la fois. Il doit attendre que le trajet soit terminé pour en réserver un nouveau.

## Estimation des économies

CarKlop calcule les économies potentielles selon le pays de destination.

### Pays supportés

| Pays | Code | Points forts |
|------|------|--------------|
| 🇩🇪 Allemagne | DE | Alimentaire et bières moins chers |
| 🇱🇺 Luxembourg | LU | Carburant et tabac très avantageux |
| 🇧🇪 Belgique | BE | Chocolat et bières à prix réduit |
| 🇪🇸 Espagne | ES | Alimentation et tabac économiques |
| 🇮🇹 Italie | IT | Produits alimentaires avantageux |
| 🇨🇭 Suisse | CH | ⚠️ Plus cher - idéal pour travailleurs frontaliers |
| 🇦🇩 Andorre | AD | Tabac et alcool très avantageux (duty-free) |

### GET /api/savings/estimate
```json
{
  "country": "DE",
  "countryName": "Allemagne",
  "budget": 200,
  "estimatedSavings": 25,
  "breakdown": {
    "alimentaire": 15,
    "alcool": 8,
    "carburant": 2,
    "tabac": 2
  },
  "description": "Alimentaire et bières moins chers",
  "message": "Économie estimée : ~25€ sur un budget de 200€"
}
```

### GET /api/me/stats
```json
{
  "totalSavings": 127,
  "tripsAsPassenger": 5,
  "tripsAsDriver": 2,
  "monthlyStats": [
    {
      "month": "2024-12",
      "label": "December 2024",
      "savings": 45,
      "trips": 2
    }
  ],
  "message": "127€ économisés sur 5 trajets"
}
```

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

### Couverture des tests (134 tests)

| Fichier | Tests | Fonctionnalités |
|---------|-------|-----------------|
| UserTest | 30 | Inscription, login, profil, avatar, vérification email |
| TripTest | 7 | CRUD trajets, filtres |
| BookingTest | 8 | Réservation, confirmation, paiement |
| CancellationTest | 8 | Annulations, remboursements |
| MessageTest | 9 | Conversations, messages |
| ReviewTest | 7 | Avis, notation |
| StripeConnectTest | 6 | Onboarding conducteur |
| StripeWebhookTest | 5 | Webhooks paiement |
| NotificationTest | 11 | Tokens push, notifications |
| TransferTest | 6 | Transferts automatiques |
| SocialAuthTest | 7 | Google, Apple Sign In |
| PasswordResetTest | 12 | Mot de passe oublié, reset |
| ProfileTest | 6 | Profil public, bio, stats |
| SavingsTest | 12 | Économies par pays, stats utilisateur |

## Endpoints API

### Auth
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/login` | Connexion (email/password) |
| POST | `/api/users` | Inscription |
| POST | `/api/auth/google` | Connexion Google |
| POST | `/api/auth/apple` | Connexion Apple |
| GET | `/api/verify-email/{token}` | Vérification email |
| POST | `/api/resend-verification` | Renvoyer email vérification |
| POST | `/api/forgot-password` | Demander reset mot de passe |
| GET | `/api/reset-password/verify/{token}` | Vérifier token reset |
| POST | `/api/reset-password` | Réinitialiser mot de passe |

### Profil
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/me` | Mon profil |
| GET | `/api/me/stats` | Mes statistiques (économies, trajets) |
| GET | `/api/me/trips` | Mes trajets (conducteur) |
| GET | `/api/me/trips/{id}` | Détail d'un trajet avec passagers |
| GET | `/api/me/bookings` | Mes réservations (passager) |
| PATCH | `/api/users/{id}` | Modifier mon profil |
| POST | `/api/upload/avatar` | Upload avatar |
| GET | `/api/users/{id}/profile` | Profil public d'un utilisateur |

### Trajets
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/trips` | Liste trajets (filtres disponibles) |
| GET | `/api/trips/{id}` | Détail trajet |
| POST | `/api/trips` | Créer trajet |
| PATCH | `/api/trips/{id}` | Modifier trajet |

### Réservations
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/bookings/create` | Créer une réservation |
| GET | `/api/bookings/{id}/details` | Détails d'une réservation |
| POST | `/api/bookings/{id}/confirm` | Confirmer après paiement Stripe |
| POST | `/api/bookings/{id}/complete` | Confirmer fin de trajet (passager) |
| POST | `/api/bookings/{id}/cancel` | Annuler (passager) |
| POST | `/api/bookings/trip/{tripId}/cancel` | Annuler trajet complet (conducteur) |

### Messages
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/messages/conversations` | Mes conversations |
| GET | `/api/messages/conversations/{id}` | Messages d'une conversation |
| POST | `/api/messages/conversations/{id}/send` | Envoyer message |
| POST | `/api/messages/start/{bookingId}` | Démarrer conversation |

### Avis
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/reviews` | Laisser un avis |
| GET | `/api/reviews/user/{id}` | Avis d'un utilisateur |

### Notifications
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/notifications/register-token` | Enregistrer token push |
| GET | `/api/notifications` | Mes notifications |
| POST | `/api/notifications/{id}/read` | Marquer comme lue |
| POST | `/api/notifications/read-all` | Tout marquer comme lu |

### Stripe Connect
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/stripe/connect/onboarding` | Créer compte + lien onboarding |
| GET | `/api/stripe/connect/status` | Statut compte Connect |

### Webhook
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/webhook/stripe` | Webhook Stripe |

### Économies
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/savings/estimate?country=DE&budget=200` | Estimation des économies |
| GET | `/api/savings/countries` | Liste des pays avec pourcentages |

## Flux de réservation
```
1. Passager recherche un trajet
2. POST /api/bookings/create → reçoit client_secret Stripe
3. Passager paie via Stripe (frontend)
4. POST /api/bookings/{id}/confirm → booking "paid", conversation créée
5. Trajet effectué...
6. POST /api/bookings/{id}/complete → booking "completed", transfert conducteur
7. POST /api/reviews → passager laisse un avis
```

## Commandes utiles
```bash
# Vider le cache
php bin/console cache:clear

# Voir les routes
php bin/console debug:router

# Lancer le scheduler (production)
php bin/console messenger:consume scheduler_default

# Compléter manuellement les trajets expirés
php bin/console messenger:consume async --limit=10
```

## Configuration OAuth

### Google
1. Créer un projet sur [Google Cloud Console](https://console.cloud.google.com/)
2. Activer l'API Google+ et créer des identifiants OAuth 2.0
3. Ajouter le Client ID dans `GOOGLE_CLIENT_ID`

### Apple
1. Créer un App ID sur [Apple Developer](https://developer.apple.com/)
2. Activer "Sign in with Apple"
3. Créer un Services ID
4. Ajouter le Client ID dans `APPLE_CLIENT_ID`

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

## Licence

Propriétaire - Louis ZERRI
```