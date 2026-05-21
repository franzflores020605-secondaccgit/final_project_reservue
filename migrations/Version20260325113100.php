<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325113100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill customer_package_booking.submitted_by_id from lead traveler owner when missing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE customer_package_booking b
            INNER JOIN traveler t ON b.lead_traveler_id = t.id
            SET b.submitted_by_id = t.owner_id
            WHERE b.submitted_by_id IS NULL AND t.owner_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Data backfill is not safely reversible.
    }
}
