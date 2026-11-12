<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns the schema with what Doctrine expects for the Symfony app:
 * the ENUM columns become VARCHARs (the entities treat them as plain
 * strings anyway), the description column becomes LONGTEXT, indexes
 * get Doctrine-style names, the orders table gets indexes for the
 * dashboard/order-history queries, and the Messenger table is added.
 */
final class Version20261113000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align schema with Doctrine: VARCHAR types, index names, orders indexes, messenger table';
    }

    public function up(Schema $schema): void
    {
        // ENUM -> VARCHAR (keeps all existing values, same allowed sets
        // are still enforced by the app).
        $this->addSql("ALTER TABLE users CHANGE role role VARCHAR(10) DEFAULT 'customer' NOT NULL");
        $this->addSql("ALTER TABLE orders CHANGE payment_method payment_method VARCHAR(10) DEFAULT 'cod' NOT NULL");
        $this->addSql("ALTER TABLE orders CHANGE status status VARCHAR(20) DEFAULT 'pending' NOT NULL");
        $this->addSql('ALTER TABLE products CHANGE description description LONGTEXT DEFAULT NULL');

        // Doctrine-style index names so future diffs stay clean.
        $this->addSql('ALTER TABLE users RENAME INDEX email TO UNIQ_1483A5E9E7927C74');
        $this->addSql('ALTER TABLE order_items RENAME INDEX order_id TO IDX_62809DB08D9F6D38');
        $this->addSql('ALTER TABLE order_items RENAME INDEX product_id TO IDX_62809DB04584665A');

        // Recreate the user FK under its Doctrine name, keeping the
        // legacy ON DELETE CASCADE behaviour.
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY orders_ibfk_1');
        $this->addSql('ALTER TABLE orders RENAME INDEX user_id TO IDX_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        // Indexes for the dashboard stats and order-history queries.
        $this->addSql('CREATE INDEX idx_orders_created_at ON orders (created_at)');
        $this->addSql('CREATE INDEX idx_orders_status ON orders (status)');

        // Symfony Messenger table.
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                available_at DATETIME NOT NULL,
                delivered_at DATETIME DEFAULT NULL,
                INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id),
                PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP INDEX idx_orders_created_at ON orders');
        $this->addSql('DROP INDEX idx_orders_status ON orders');

        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE orders RENAME INDEX IDX_E52FFDEEA76ED395 TO user_id');
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT orders_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE");

        $this->addSql("ALTER TABLE orders CHANGE payment_method payment_method ENUM('cod','online') DEFAULT 'cod' NOT NULL");
        $this->addSql("ALTER TABLE orders CHANGE status status ENUM('pending','paid','shipped','delivered','cancelled') DEFAULT 'pending'");
        $this->addSql("ALTER TABLE users CHANGE role role ENUM('customer','admin') DEFAULT 'customer'");
        $this->addSql('ALTER TABLE products CHANGE description description TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE users RENAME INDEX UNIQ_1483A5E9E7927C74 TO email');
        $this->addSql('ALTER TABLE order_items RENAME INDEX IDX_62809DB08D9F6D38 TO order_id');
        $this->addSql('ALTER TABLE order_items RENAME INDEX IDX_62809DB04584665A TO product_id');
    }
}
