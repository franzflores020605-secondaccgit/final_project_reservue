<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow null traveler.owner_id for web bookings; widen phone columns for international numbers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE traveler MODIFY owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE traveler MODIFY phone VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_package_booking MODIFY contact_phone VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking MODIFY contact_phone VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE traveler MODIFY phone VARCHAR(40) DEFAULT NULL');
    }
}
