<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add address column to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD address LONGTEXT DEFAULT NULL AFTER email');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP address');
    }
}
