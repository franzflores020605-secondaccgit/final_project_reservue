<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replaces manual bookings + booking_traveler with customer-driven leads only.
 */
final class Version20260323200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop bookings and booking_traveler tables; add customer_package_booking.booking_kind.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking ADD booking_kind VARCHAR(32) NOT NULL DEFAULT \'package\'');
        $this->addSql('DROP TABLE IF EXISTS booking_traveler');
        $this->addSql('DROP TABLE IF EXISTS bookings');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Legacy bookings schema is not restored.');
    }
}
