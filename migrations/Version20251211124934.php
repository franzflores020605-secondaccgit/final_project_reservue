<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211124934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // create audit_log table only; avoid altering existing product table
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) DEFAULT NULL, roles JSON NOT NULL, action VARCHAR(30) NOT NULL, entity VARCHAR(180) NOT NULL, entity_id INT DEFAULT NULL, details LONGTEXT DEFAULT NULL, ip VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
