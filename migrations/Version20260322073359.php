<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Superseded: duplicate of Version20260322073320 / 73408 — must not run DDL.
 */
final class Version20260322073359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op (removed duplicate full-schema migration).';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
