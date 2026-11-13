<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Account disabling (archive-lite): users get a disabled switch the admin
 * can flip. Disabled accounts cannot sign in anymore, but every record
 * they own (orders, products) is kept - unlike deleting, which cascades.
 */
final class Version20261113150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.disabled flag (admin can block sign-in while keeping the account data)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN disabled TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN disabled');
    }
}
