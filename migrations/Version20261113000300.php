<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shipping_address column to orders table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD shipping_address LONGTEXT DEFAULT NULL AFTER payment_proof');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP shipping_address');
    }
}
