<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251212093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_id column to audit_log for linking logs to user ids';
    }

    public function up(Schema $schema): void
    {
        // add user_id column (nullable) to audit_log
        $this->addSql('ALTER TABLE audit_log ADD user_id INT DEFAULT NULL');
        // optional: index to speed queries by user_id
        $this->addSql('CREATE INDEX IDX_audit_log_user_id ON audit_log (user_id)');
    }

    public function down(Schema $schema): void
    {
        // remove index and column
        $this->addSql('DROP INDEX IDX_audit_log_user_id ON audit_log');
        $this->addSql('ALTER TABLE audit_log DROP user_id');
    }
}
