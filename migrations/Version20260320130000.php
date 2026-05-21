<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates traveler / booking_traveler and links bookings + product to user.
 * (Previously only appeared inside accidental full-schema migrations.)
 */
final class Version20260320130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add traveler, booking_traveler, bookings.owner_id, product.owner_id and align bookings columns with the entity mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE traveler (id INT AUTO_INCREMENT NOT NULL, owner_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, passport_number VARCHAR(100) DEFAULT NULL, INDEX IDX_6841F2167E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE booking_traveler (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, traveler_id INT NOT NULL, traveler_type VARCHAR(50) DEFAULT NULL, seat_number VARCHAR(10) DEFAULT NULL, meal_preference VARCHAR(50) DEFAULT NULL, INDEX IDX_53756C9B3301C60 (booking_id), INDEX IDX_53756C9B59BBE8A3 (traveler_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE booking_traveler ADD CONSTRAINT FK_53756C9B3301C60 FOREIGN KEY (booking_id) REFERENCES bookings (id)');
        $this->addSql('ALTER TABLE booking_traveler ADD CONSTRAINT FK_53756C9B59BBE8A3 FOREIGN KEY (traveler_id) REFERENCES traveler (id)');
        $this->addSql('ALTER TABLE traveler ADD CONSTRAINT FK_6841F2167E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE bookings ADD owner_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_7A853C357E3C61F9 ON bookings (owner_id)');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C357E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE bookings CHANGE booking_code booking_code VARCHAR(50) NOT NULL, CHANGE booking_status booking_status VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7A853C356F8412D6 ON bookings (booking_code)');

        $this->addSql('ALTER TABLE product ADD owner_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_D34A04AD7E3C61F9 ON product (owner_id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD7E3C61F9');
        $this->addSql('DROP INDEX IDX_D34A04AD7E3C61F9 ON product');
        $this->addSql('ALTER TABLE product DROP owner_id');

        $this->addSql('DROP INDEX UNIQ_7A853C356F8412D6 ON bookings');
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_7A853C357E3C61F9');
        $this->addSql('DROP INDEX IDX_7A853C357E3C61F9 ON bookings');
        $this->addSql('ALTER TABLE bookings DROP owner_id');
        $this->addSql('ALTER TABLE bookings CHANGE booking_code booking_code VARCHAR(100) NOT NULL, CHANGE booking_status booking_status VARCHAR(255) NOT NULL');

        $this->addSql('ALTER TABLE booking_traveler DROP FOREIGN KEY FK_53756C9B3301C60');
        $this->addSql('ALTER TABLE booking_traveler DROP FOREIGN KEY FK_53756C9B59BBE8A3');
        $this->addSql('ALTER TABLE traveler DROP FOREIGN KEY FK_6841F2167E3C61F9');
        $this->addSql('DROP TABLE booking_traveler');
        $this->addSql('DROP TABLE traveler');
    }
}
