<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product.image_path and product.show_on_book_page for public book page.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD image_path VARCHAR(500) DEFAULT NULL, ADD show_on_book_page TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP image_path, DROP show_on_book_page');
    }
}
