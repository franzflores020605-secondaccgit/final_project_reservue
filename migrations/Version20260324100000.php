<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map customer_package_booking status to pending / completed / cancelled.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE customer_package_booking SET status = 'pending' WHERE status IN ('new', 'in_review')");
        $this->addSql("UPDATE customer_package_booking SET status = 'completed' WHERE status = 'closed'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE customer_package_booking SET status = 'new' WHERE status = 'pending'");
        $this->addSql("UPDATE customer_package_booking SET status = 'closed' WHERE status IN ('completed', 'cancelled')");
    }
}
