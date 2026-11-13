<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Staff-role groundwork: products get a created_by owner column so staff
 * members can only manage the records they created themselves. Admin and
 * seed products keep NULL (treated as shop-owned/admin records). Also
 * aligns the newer TEXT address columns with Doctrine's LONGTEXT mapping.
 */
final class Version20261113110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add products.created_by owner (staff records) + LONGTEXT address columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5ADE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B3BA5A5ADE12AB56 ON products (created_by)');

        $this->addSql('ALTER TABLE users CHANGE address address LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders CHANGE shipping_address shipping_address LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5ADE12AB56');
        $this->addSql('DROP INDEX IDX_B3BA5A5ADE12AB56 ON products');
        $this->addSql('ALTER TABLE products DROP created_by');

        $this->addSql('ALTER TABLE users CHANGE address address TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders CHANGE shipping_address shipping_address TEXT DEFAULT NULL');
    }
}
