<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260321122928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add travel_package and customer_package_booking tables for public customer booking page.';
    }

    public function up(Schema $schema): void
    {
        // Recover from a previously failed run that may have created only `travel_package`.
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS customer_package_booking');
        $this->addSql('DROP TABLE IF EXISTS travel_package');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');

        $this->addSql('CREATE TABLE travel_package (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, short_description LONGTEXT DEFAULT NULL, image_path VARCHAR(500) NOT NULL, duration_label VARCHAR(80) NOT NULL, price_per_person NUMERIC(10, 2) NOT NULL, tour_category VARCHAR(32) NOT NULL, is_published TINYINT(1) DEFAULT 1 NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1F2BD084989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE customer_package_booking (id INT AUTO_INCREMENT NOT NULL, travel_package_id INT NOT NULL, travel_date DATE NOT NULL, number_of_travelers INT NOT NULL, contact_full_name VARCHAR(160) NOT NULL, contact_email VARCHAR(180) NOT NULL, contact_phone VARCHAR(40) DEFAULT NULL, special_requests LONGTEXT DEFAULT NULL, reference_code VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_147FCEC8D6C838 (reference_code), INDEX IDX_147FCEC8ADF94A2C (travel_package_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_package_booking ADD CONSTRAINT FK_147FCEC8ADF94A2C FOREIGN KEY (travel_package_id) REFERENCES travel_package (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking DROP FOREIGN KEY FK_147FCEC8ADF94A2C');
        $this->addSql('DROP TABLE customer_package_booking');
        $this->addSql('DROP TABLE travel_package');
    }
}
