<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link bookings to customer_package_booking for web requests; ensure nullable owner on bookings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookings ADD customer_package_booking_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7A853C356C3E94B1 ON bookings (customer_package_booking_id)');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_bookings_customer_package_booking FOREIGN KEY (customer_package_booking_id) REFERENCES customer_package_booking (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE bookings CHANGE owner_id owner_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookings DROP FOREIGN KEY FK_bookings_customer_package_booking');
        $this->addSql('DROP INDEX UNIQ_7A853C356C3E94B1 ON bookings');
        $this->addSql('ALTER TABLE bookings DROP customer_package_booking_id');
    }
}
