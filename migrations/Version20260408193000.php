<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track whether package booking rows have deducted linked product inventory.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking ADD inventory_deducted TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking DROP inventory_deducted');
    }
}
