<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202232119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, seats_booked INT NOT NULL, price_per_seat INT NOT NULL, commission_amount INT NOT NULL, total_amount INT NOT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, stripe_transfer_id VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, paid_at DATETIME DEFAULT NULL, refunded_at DATETIME DEFAULT NULL, refunded_amount INT DEFAULT NULL, cancelled_by VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, trip_id INT NOT NULL, passenger_id INT NOT NULL, INDEX IDX_E00CEDDEA5BC2E0E (trip_id), INDEX IDX_E00CEDDE4502E565 (passenger_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE trip (id INT AUTO_INCREMENT NOT NULL, departure_city VARCHAR(100) NOT NULL, departure_address VARCHAR(255) DEFAULT NULL, departure_country VARCHAR(20) DEFAULT NULL, destination_city VARCHAR(100) NOT NULL, destination_address VARCHAR(255) DEFAULT NULL, destination_country VARCHAR(20) NOT NULL, departure_at DATETIME NOT NULL, return_at DATETIME NOT NULL, available_seats INT NOT NULL, price_per_seat INT NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, driver_id INT NOT NULL, INDEX IDX_7656F53BC3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(50) NOT NULL, last_name VARCHAR(50) NOT NULL, phone VARCHAR(20) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, is_verified TINYINT NOT NULL, stripe_account_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE4502E565 FOREIGN KEY (passenger_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53BC3423909 FOREIGN KEY (driver_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA5BC2E0E');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE4502E565');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53BC3423909');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE trip');
        $this->addSql('DROP TABLE user');
    }
}
