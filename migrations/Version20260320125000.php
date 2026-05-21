<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320125000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make user.is_verified a required boolean (NOT NULL DEFAULT 0).';
    }

    public function up(Schema $schema): void
    {
        // Existing rows may still contain NULL, so convert them first
        $this->addSql('UPDATE user SET is_verified = 0 WHERE is_verified IS NULL');
        $this->addSql('ALTER TABLE user MODIFY is_verified TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user MODIFY is_verified TINYINT(1) DEFAULT NULL');
    }
}

