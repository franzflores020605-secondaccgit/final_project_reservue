<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove travel_package.slug and travel_package.sort_order (URL slug and manual sort order).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1F2BD084989D9B62 ON travel_package');
        $this->addSql('ALTER TABLE travel_package DROP COLUMN slug, DROP COLUMN sort_order');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_package ADD slug VARCHAR(200) NOT NULL, ADD sort_order INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1F2BD084989D9B62 ON travel_package (slug)');
    }
}
