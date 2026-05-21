<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Split booking contact name; add passport; link lead traveler record.
 */
final class Version20260324120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace customer_package_booking.contact_full_name with first/last/passport; add lead_traveler_id FK.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking ADD contact_first_name VARCHAR(100) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE customer_package_booking ADD contact_last_name VARCHAR(100) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE customer_package_booking ADD contact_passport_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_package_booking ADD lead_traveler_id INT DEFAULT NULL');

        $this->addSql("UPDATE customer_package_booking SET
            contact_first_name = CASE
                WHEN contact_full_name IS NULL OR TRIM(contact_full_name) = '' THEN 'Unknown'
                WHEN LOCATE(' ', TRIM(contact_full_name)) > 0 THEN SUBSTRING_INDEX(TRIM(contact_full_name), ' ', 1)
                ELSE TRIM(contact_full_name)
            END,
            contact_last_name = CASE
                WHEN contact_full_name IS NULL OR TRIM(contact_full_name) = '' THEN 'Guest'
                WHEN LOCATE(' ', TRIM(contact_full_name)) > 0 THEN TRIM(SUBSTRING(TRIM(contact_full_name), LOCATE(' ', TRIM(contact_full_name)) + 1))
                ELSE 'Guest'
            END");

        $this->addSql("UPDATE customer_package_booking SET contact_passport_number = 'Not provided' WHERE contact_passport_number IS NULL");
        $this->addSql('ALTER TABLE customer_package_booking MODIFY contact_passport_number VARCHAR(100) NOT NULL');

        $this->addSql('ALTER TABLE customer_package_booking DROP COLUMN contact_full_name');

        $this->addSql('CREATE INDEX IDX_CPB_LEAD_TRAVELER ON customer_package_booking (lead_traveler_id)');
        $this->addSql('ALTER TABLE customer_package_booking ADD CONSTRAINT FK_CPB_LEAD_TRAVELER FOREIGN KEY (lead_traveler_id) REFERENCES traveler (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking DROP FOREIGN KEY FK_CPB_LEAD_TRAVELER');
        $this->addSql('DROP INDEX IDX_CPB_LEAD_TRAVELER ON customer_package_booking');
        $this->addSql('ALTER TABLE customer_package_booking ADD contact_full_name VARCHAR(160) NOT NULL DEFAULT \'\'');
        $this->addSql("UPDATE customer_package_booking SET contact_full_name = TRIM(CONCAT(COALESCE(contact_first_name, ''), ' ', COALESCE(contact_last_name, '')))");
        $this->addSql('ALTER TABLE customer_package_booking DROP COLUMN lead_traveler_id');
        $this->addSql('ALTER TABLE customer_package_booking DROP COLUMN contact_passport_number');
        $this->addSql('ALTER TABLE customer_package_booking DROP COLUMN contact_last_name');
        $this->addSql('ALTER TABLE customer_package_booking DROP COLUMN contact_first_name');
        $this->addSql('ALTER TABLE customer_package_booking MODIFY contact_full_name VARCHAR(160) NOT NULL');
    }
}
