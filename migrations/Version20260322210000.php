<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add traveler.email and traveler.phone for public booking submissions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE traveler ADD email VARCHAR(180) DEFAULT NULL, ADD phone VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE traveler DROP email, DROP phone');
    }
}
