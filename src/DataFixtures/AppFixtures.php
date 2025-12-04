<?php

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // === USERS ===
        $users = [];

        // Admin
        $admin = new User();
        $admin->setEmail('admin@carklop.fr');
        $admin->setFirstName('Louis');
        $admin->setLastName('Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $admin->setIsVerified(true);
        $admin->setCreatedAt(new \DateTimeImmutable('-6 months'));
        $manager->persist($admin);
        $users['admin'] = $admin;

        // Conducteurs
        $driversData = [
            ['marie@test.fr', 'Marie', 'Laurent', 'acct_test_marie', '+33612345678'],
            ['thomas@test.fr', 'Thomas', 'Renaud', 'acct_test_thomas', '+33623456789'],
            ['sophie@test.fr', 'Sophie', 'Martin', 'acct_test_sophie', '+33634567890'],
        ];

        foreach ($driversData as $i => $d) {
            $user = new User();
            $user->setEmail($d[0]);
            $user->setFirstName($d[1]);
            $user->setLastName($d[2]);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $user->setIsVerified(true);
            $user->setStripeAccountId($d[3]);
            $user->setPhone($d[4]);
            $user->setCreatedAt(new \DateTimeImmutable('-' . (5 - $i) . ' months'));
            $manager->persist($user);
            $users['driver' . ($i + 1)] = $user;
        }

        // Passagers
        $passengersData = [
            ['jean@test.fr', 'Jean', 'Dupont', '+33645678901'],
            ['claire@test.fr', 'Claire', 'Bernard', '+33656789012'],
            ['pierre@test.fr', 'Pierre', 'Durand', '+33667890123'],
        ];

        foreach ($passengersData as $i => $p) {
            $user = new User();
            $user->setEmail($p[0]);
            $user->setFirstName($p[1]);
            $user->setLastName($p[2]);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $user->setIsVerified(true);
            $user->setPhone($p[3]);
            $user->setCreatedAt(new \DateTimeImmutable('-' . (4 - $i) . ' months'));
            $manager->persist($user);
            $users['passenger' . ($i + 1)] = $user;
        }

        // === TRIPS ===
        $trips = [];

        $tripsData = [
            [
                'driver' => 'driver1',
                'departureCity' => 'Strasbourg',
                'departureAddress' => 'Place de la Gare',
                'departureCountry' => 'FR',
                'destinationCity' => 'Kehl',
                'destinationAddress' => 'Centre commercial',
                'destinationCountry' => 'DE',
                'seats' => 3,
                'price' => 800,
                'status' => 'published',
                'departureIn' => '+2 days 09:00',
                'returnIn' => '+2 days 14:00',
                'description' => 'Je fais ce trajet tous les samedis. RDV devant la gare.',
            ],
            [
                'driver' => 'driver2',
                'departureCity' => 'Metz',
                'departureAddress' => 'Gare de Metz',
                'departureCountry' => 'FR',
                'destinationCity' => 'Luxembourg',
                'destinationAddress' => 'Centre-ville',
                'destinationCountry' => 'LU',
                'seats' => 2,
                'price' => 1500,
                'status' => 'published',
                'departureIn' => '+3 days 08:00',
                'returnIn' => '+3 days 15:00',
                'description' => 'Trajet confortable, voiture climatisée.',
            ],
            [
                'driver' => 'driver3',
                'departureCity' => 'Nice',
                'departureAddress' => 'Promenade des Anglais',
                'departureCountry' => 'FR',
                'destinationCity' => 'Vintimille',
                'destinationAddress' => 'Marché',
                'destinationCountry' => 'IT',
                'seats' => 4,
                'price' => 1200,
                'status' => 'published',
                'departureIn' => '+5 days 07:00',
                'returnIn' => '+5 days 13:00',
                'description' => 'Idéal pour le marché du vendredi !',
            ],
            [
                'driver' => 'driver1',
                'departureCity' => 'Strasbourg',
                'departureAddress' => 'Place de la Gare',
                'departureCountry' => 'FR',
                'destinationCity' => 'Baden-Baden',
                'destinationAddress' => 'Zentrum',
                'destinationCountry' => 'DE',
                'seats' => 3,
                'price' => 1000,
                'status' => 'completed',
                'departureIn' => '-7 days 09:00',
                'returnIn' => '-7 days 15:00',
                'description' => 'Trajet terminé.',
            ],
        ];

        foreach ($tripsData as $i => $t) {
            $trip = new Trip();
            $trip->setDriver($users[$t['driver']]);
            $trip->setDepartureCity($t['departureCity']);
            $trip->setDepartureAddress($t['departureAddress']);
            $trip->setDepartureCountry($t['departureCountry']);
            $trip->setDestinationCity($t['destinationCity']);
            $trip->setDestinationAddress($t['destinationAddress']);
            $trip->setDestinationCountry($t['destinationCountry']);
            $trip->setAvailableSeats($t['seats']);
            $trip->setPricePerSeat($t['price']);
            $trip->setStatus($t['status']);
            $trip->setDepartureAt(new \DateTimeImmutable($t['departureIn']));
            $trip->setReturnAt(new \DateTimeImmutable($t['returnIn']));
            $trip->setDescription($t['description']);
            $trip->setCreatedAt(new \DateTimeImmutable('-' . (10 - $i) . ' days'));
            $manager->persist($trip);
            $trips[] = $trip;
        }

        // === BOOKINGS (sur le trajet terminé) ===
        $completedTrip = $trips[3];

        $booking1 = new Booking();
        $booking1->setTrip($completedTrip);
        $booking1->setPassenger($users['passenger1']);
        $booking1->setSeatsBooked(1);
        $booking1->setPricePerSeat(1000);
        $booking1->setCommissionAmount(150);
        $booking1->setTotalAmount(1150);
        $booking1->setStatus('completed');
        $booking1->setStripePaymentIntentId('pi_test_123');
        $booking1->setStripeTransferId('tr_test_123');
        $booking1->setPaidAt(new \DateTimeImmutable('-8 days'));
        $booking1->setCreatedAt(new \DateTimeImmutable('-8 days'));
        $manager->persist($booking1);

        $booking2 = new Booking();
        $booking2->setTrip($completedTrip);
        $booking2->setPassenger($users['passenger2']);
        $booking2->setSeatsBooked(2);
        $booking2->setPricePerSeat(1000);
        $booking2->setCommissionAmount(300);
        $booking2->setTotalAmount(2300);
        $booking2->setStatus('completed');
        $booking2->setStripePaymentIntentId('pi_test_456');
        $booking2->setStripeTransferId('tr_test_456');
        $booking2->setPaidAt(new \DateTimeImmutable('-8 days'));
        $booking2->setCreatedAt(new \DateTimeImmutable('-8 days'));
        $manager->persist($booking2);

        // === CONVERSATIONS ===
        $conv1 = new Conversation();
        $conv1->setBooking($booking1);
        $conv1->setDriver($completedTrip->getDriver());
        $conv1->setPassenger($users['passenger1']);
        $conv1->setCreatedAt(new \DateTimeImmutable('-8 days'));
        $conv1->setUpdatedAt(new \DateTimeImmutable('-7 days'));
        $manager->persist($conv1);

        $conv2 = new Conversation();
        $conv2->setBooking($booking2);
        $conv2->setDriver($completedTrip->getDriver());
        $conv2->setPassenger($users['passenger2']);
        $conv2->setCreatedAt(new \DateTimeImmutable('-8 days'));
        $conv2->setUpdatedAt(new \DateTimeImmutable('-6 days'));
        $manager->persist($conv2);

        // === MESSAGES ===
        $messagesData = [
            [$conv1, $users['passenger1'], 'Bonjour ! À quelle heure exactement le départ ?', '-8 days 10:00'],
            [$conv1, $users['driver1'], 'Bonjour ! RDV à 8h45 devant la gare.', '-8 days 10:15'],
            [$conv1, $users['passenger1'], 'Parfait, merci !', '-8 days 10:20'],
            [$conv2, $users['passenger2'], 'Bonjour, est-ce qu\'il y a de la place pour un sac de course ?', '-8 days 11:00'],
            [$conv2, $users['driver1'], 'Oui pas de souci, le coffre est grand !', '-8 days 11:30'],
        ];

        foreach ($messagesData as $m) {
            $message = new Message();
            $message->setConversation($m[0]);
            $message->setSender($m[1]);
            $message->setContent($m[2]);
            $message->setIsRead(true);
            $message->setCreatedAt(new \DateTimeImmutable($m[3]));
            $manager->persist($message);
        }

        // === REVIEWS ===
        $review1 = new Review();
        $review1->setBooking($booking1);
        $review1->setAuthor($users['passenger1']);
        $review1->setTarget($users['driver1']);
        $review1->setRating(5);
        $review1->setComment('Super conductrice, très ponctuelle et sympathique !');
        $review1->setCreatedAt(new \DateTimeImmutable('-6 days'));
        $manager->persist($review1);

        $review2 = new Review();
        $review2->setBooking($booking2);
        $review2->setAuthor($users['passenger2']);
        $review2->setTarget($users['driver1']);
        $review2->setRating(4);
        $review2->setComment('Bon trajet, je recommande.');
        $review2->setCreatedAt(new \DateTimeImmutable('-5 days'));
        $manager->persist($review2);

        $manager->flush();
    }
}