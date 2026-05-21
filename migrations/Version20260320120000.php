<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy booking_id column from bookings (primary key id remains).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookings DROP COLUMN booking_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookings ADD booking_id INT NOT NULL DEFAULT 0');
    }
}
