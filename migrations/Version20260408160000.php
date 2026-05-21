<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire "confirmed" booking status: map existing rows to "completed" so the enum matches the DB.
 */
final class Version20260408160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map customer_package_booking.status from confirmed to completed (remove Confirmed state).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE customer_package_booking SET status = 'completed' WHERE status = 'confirmed'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
