<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link travel_package to category; add package_products pivot; drop tour_category enum column.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE package_products (package_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_PACKAGE_PRODUCTS_PRODUCT (product_id), PRIMARY KEY(package_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE package_products ADD CONSTRAINT FK_4D5617ACF44CABFF FOREIGN KEY (package_id) REFERENCES travel_package (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE package_products ADD CONSTRAINT FK_4D5617AC4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE travel_package ADD category_id INT DEFAULT NULL');
        $this->addSql('UPDATE travel_package SET category_id = (SELECT MIN(id) FROM category) WHERE category_id IS NULL');
        $this->addSql('ALTER TABLE travel_package MODIFY category_id INT NOT NULL');
        $this->addSql('ALTER TABLE travel_package ADD CONSTRAINT FK_1F2BD08412469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_1F2BD08412469DE2 ON travel_package (category_id)');
        $this->addSql('ALTER TABLE travel_package DROP COLUMN tour_category');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE travel_package ADD tour_category VARCHAR(32) NOT NULL DEFAULT \'beach\'');
        $this->addSql('ALTER TABLE travel_package DROP FOREIGN KEY FK_1F2BD08412469DE2');
        $this->addSql('DROP INDEX IDX_1F2BD08412469DE2 ON travel_package');
        $this->addSql('ALTER TABLE travel_package DROP COLUMN category_id');
        $this->addSql('ALTER TABLE package_products DROP FOREIGN KEY FK_4D5617ACF44CABFF');
        $this->addSql('ALTER TABLE package_products DROP FOREIGN KEY FK_4D5617AC4584665A');
        $this->addSql('DROP TABLE package_products');
    }
}
