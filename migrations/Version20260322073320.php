<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Superseded: this file duplicated the entire schema and broke the incremental chain.
 * Real DDL lives in the dated migrations before and after this version.
 */
final class Version20260322073320 extends AbstractMigration
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
