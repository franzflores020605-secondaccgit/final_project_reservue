<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link customer bookings to a catalog Product when the customer books an independent inventory item.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking ADD booked_product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_package_booking ADD CONSTRAINT FK_BOOKED_PRODUCT FOREIGN KEY (booked_product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CPB_BOOKED_PRODUCT ON customer_package_booking (booked_product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_package_booking DROP FOREIGN KEY FK_BOOKED_PRODUCT');
        $this->addSql('DROP INDEX IDX_CPB_BOOKED_PRODUCT ON customer_package_booking');
        $this->addSql('ALTER TABLE customer_package_booking DROP booked_product_id');
    }
}
