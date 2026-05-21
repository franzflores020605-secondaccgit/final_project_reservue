<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407132200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_package_booking DROP FOREIGN KEY FK_147FCEC8ADF94A2C');
        $this->addSql('ALTER TABLE customer_package_booking CHANGE travel_package_id travel_package_id INT DEFAULT NULL, CHANGE contact_first_name contact_first_name VARCHAR(100) NOT NULL, CHANGE contact_last_name contact_last_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE customer_package_booking ADD CONSTRAINT FK_147FCEC8ADF94A2C FOREIGN KEY (travel_package_id) REFERENCES travel_package (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_package_booking DROP FOREIGN KEY FK_147FCEC8ADF94A2C');
        $this->addSql('ALTER TABLE customer_package_booking CHANGE travel_package_id travel_package_id INT NOT NULL, CHANGE contact_first_name contact_first_name VARCHAR(100) DEFAULT \'\' NOT NULL, CHANGE contact_last_name contact_last_name VARCHAR(100) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE customer_package_booking ADD CONSTRAINT FK_147FCEC8ADF94A2C FOREIGN KEY (travel_package_id) REFERENCES travel_package (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
