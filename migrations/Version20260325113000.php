<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link customer_package_booking to submitting user for customer "My Trips" dashboard.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking ADD submitted_by_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CPB_SUBMITTED_BY ON customer_package_booking (submitted_by_id)');
        $this->addSql('ALTER TABLE customer_package_booking ADD CONSTRAINT FK_CPB_SUBMITTED_BY FOREIGN KEY (submitted_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking DROP FOREIGN KEY FK_CPB_SUBMITTED_BY');
        $this->addSql('DROP INDEX IDX_CPB_SUBMITTED_BY ON customer_package_booking');
        $this->addSql('ALTER TABLE customer_package_booking DROP COLUMN submitted_by_id');
    }
}
