<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the tables with the data from the old site's database.
 * The passwords are the original bcrypt hashes, so the existing
 * accounts keep working with the same credentials.
 */
final class Version20261112000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed users, products, orders and order_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
            ('1', 'Sam', 'plantkeller@gmail.com', '$2y$10$ZT4i73ut5WTMc3RKQmZKhuh0NfWXPMxrsCQGtes.psWVxEPX8JMEq', 'admin', '2026-09-08 23:24:27'),
            ('3', 'testu123', 'test1@gmail.com', '$2y$10$3s61qbRx4NqZDNpwM4gaquM45eUoAHYbAwXzNiVOWdq/VWCxbOEM2', 'customer', '2026-09-09 00:02:08'),
            ('4', 'uzziahram', 'uzziahram@gmail.com', '$2y$10$ZGyTq2CDzRn5N2MbRvP9.eyYjCQpJsLQAfir3UsA4AFKyLLTf7TRm', 'customer', '2026-09-09 01:59:18'),
            ('5', 'FemboyGaming121', 'notfemboygaming@gmail.com', '$2y$10$c6ksVc3HC.ihmGQ9E8vd1OwYt9DunaEwEcjGZjygcWNNsbcRsNVt.', 'customer', '2026-09-09 06:24:52'),
            ('6', 'testu2', 'testu2@gmail.com', '$2y$10$8SM1NRCmDcA0E35tQA1qW.aAO0T9A9U0L4uDqCUqD1pIDWoHxK4xi', 'customer', '2026-09-10 06:03:50'),
            ('7', 'Driver12', 'driver12@gmail.com', '$2y$10$dHwFQlS0K6nKOa6f7vcFN.646Fba3wla/uNZJO3yE/3QnTYA7hrGG', 'admin', '2026-09-10 06:07:41'),
            ('8', 'Driver13', 'driver13@orcullocon.com', '$2y$10$wU0F6dAZW2LGmQgBJSRZ8O0t2pIG9yupEYQ7WXGBqLBZuGorhH8K2', 'admin', '2026-09-10 06:56:07');

            INSERT INTO `products` (`id`, `name`, `description`, `price`, `stock`, `image_path`, `created_at`) VALUES
            ('1', 'Orcullo MixBox', 'Premium custom MixBox layout for precise directional inputs.', '199.99', '10', 'Assets/Orucllo_MixBox.png', '2026-09-06 21:51:12'),
            ('2', 'Brook Wingman P5', 'Tournament-grade controller converter for ultimate compatibility.', '49.99', '5', 'Assets/BROOK-WINGMAN-P5__72173 1.png', '2026-09-06 21:51:12'),
            ('3', 'Sanwa Arcade Parts', 'Industry-standard buttons and joysticks for custom builds.', '14.99', '5', 'Assets/Sanwa.png', '2026-09-06 21:51:12'),
            ('4', 'Orcullo Leverless', 'Ergonomic all-button arcade controller for fighting games.', '219.99', '19', 'Assets/LEVERLESS.png', '2026-09-06 21:51:12'),
            ('15', 'MayFlashF500', '', '100.00', '2', 'uploads/1788905166_MayFlashF500.png', '2026-09-09 06:06:06'),
            ('18', 'Obsidian 2', 'test', '6767.00', '3', 'uploads/1790965044_3e3e764df8994938bc105c43f5adfd56.jpg', '2026-10-03 02:17:24');

            INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `payment_method`, `payment_proof`, `status`, `created_at`) VALUES
            ('8', '3', '369.99', 'cod', NULL, 'delivered', '2026-09-09 03:17:49'),
            ('9', '4', '150.00', 'cod', NULL, 'delivered', '2026-09-09 03:20:52'),
            ('10', '3', '214.98', 'cod', NULL, 'shipped', '2026-09-09 14:07:21'),
            ('11', '6', '199.99', 'cod', NULL, 'delivered', '2026-09-10 06:04:15'),
            ('12', '6', '1300.00', 'cod', NULL, 'delivered', '2026-09-10 06:36:22'),
            ('14', '1', '399.99', 'cod', NULL, 'shipped', '2026-09-10 06:53:36'),
            ('15', '6', '229.98', 'cod', NULL, 'shipped', '2026-09-10 06:54:34'),
            ('16', '6', '229.98', 'cod', NULL, 'pending', '2026-09-10 07:09:57'),
            ('17', '7', '89.94', 'cod', NULL, 'paid', '2026-09-10 07:40:22'),
            ('18', '4', '100.00', 'cod', NULL, 'paid', '2026-09-10 07:57:39'),
            ('19', '7', '499.90', 'cod', NULL, 'pending', '2026-09-10 07:59:36'),
            ('20', '3', '100.00', 'cod', NULL, 'pending', '2026-09-10 09:28:05'),
            ('21', '6', '150.00', 'cod', NULL, 'pending', '2026-09-10 09:39:15'),
            ('22', '6', '249.95', 'cod', NULL, 'paid', '2026-09-10 09:44:18'),
            ('23', '1', '100.00', 'cod', NULL, 'pending', '2026-09-10 09:49:45'),
            ('25', '1', '100.00', 'online', 'uploads/payment_proofs/1789017315_GcaashProof.jpeg', 'pending', '2026-09-10 13:15:15'),
            ('26', '6', '100.00', 'online', 'uploads/payment_proofs/1789021073_PaymentQR.jpeg', 'pending', '2026-09-10 14:17:53'),
            ('27', '7', '100.00', 'online', 'uploads/payment_proofs/1789021758_GcaashProof.jpeg', 'cancelled', '2026-09-10 14:29:18'),
            ('28', '6', '219.99', 'online', 'uploads/payment_proofs/1789025703_GcaashProof.jpeg', 'pending', '2026-09-10 15:35:03'),
            ('29', '6', '100.00', 'online', 'uploads/payment_proofs/1790964779_LETSGOOOHIIICOCK.jpeg', 'pending', '2026-10-03 02:12:59'),
            ('30', '6', '150.00', 'cod', NULL, 'pending', '2026-10-03 02:14:11');

            INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `item_type`, `button_color`, `artwork_path`, `price`) VALUES
            ('1', '8', NULL, 'Orcullo Leverless', 'Standard', '', '219.99'),
            ('2', '8', NULL, 'arcade_stick', 'black', '', '150.00'),
            ('3', '9', NULL, 'arcade_stick', 'black', '', '150.00'),
            ('4', '10', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('5', '10', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('6', '10', NULL, 'arcade_stick', 'white', '', '150.00'),
            ('7', '11', NULL, 'Orcullo MixBox', 'Standard', '', '199.99'),
            ('8', '12', NULL, 'Obsidian 2', 'Standard', '', '1200.00'),
            ('9', '12', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('12', '14', NULL, 'Orcullo MixBox', 'Standard', '', '199.99'),
            ('13', '14', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('14', '14', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('15', '15', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('16', '15', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('17', '15', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('18', '15', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('19', '16', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('20', '16', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('21', '16', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('22', '16', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('23', '17', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('24', '17', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('25', '17', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('26', '17', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('27', '17', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('28', '17', NULL, 'Sanwa Arcade Parts', 'Standard', '', '14.99'),
            ('29', '18', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('30', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('31', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('32', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('33', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('34', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('35', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('36', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('37', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('38', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('39', '19', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('40', '20', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('41', '21', NULL, 'arcade_stick', 'white', 'uploads/1789004348_41OBtCtYloL.webp', '150.00'),
            ('42', '22', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('43', '22', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('44', '22', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('45', '22', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('46', '22', NULL, 'Brook Wingman P5', 'Standard', '', '49.99'),
            ('47', '23', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('49', '25', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('50', '26', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('51', '27', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('52', '28', NULL, 'Orcullo Leverless', 'Standard', '', '219.99'),
            ('53', '29', NULL, 'MayFlashF500', 'Standard', '', '100.00'),
            ('54', '30', NULL, 'arcade_stick', 'red', 'uploads/1790964847_892e1fb9-f750-4c00-a9ca-721d444f4b11.jpeg', '150.00');
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM order_items');
        $this->addSql('DELETE FROM orders');
        $this->addSql('DELETE FROM products');
        $this->addSql('DELETE FROM users');
    }
}
