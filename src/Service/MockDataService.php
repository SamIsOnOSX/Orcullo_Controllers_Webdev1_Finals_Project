<?php

namespace App\Service;

class MockDataService
{
    public function getProducts(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Orcullo HitBox Pro Controller',
                'description' => 'All-button leverless arcade controller designed for precision fighting game inputs.',
                'price' => '249.99',
                'stock' => 12,
                'imagePath' => 'Assets/HeroImage1.png',
            ],
            [
                'id' => 2,
                'name' => 'Orcullo Ergonomic Grip Custom',
                'description' => 'Custom textured back paddles and hall effect sticks for competitive FPS gaming.',
                'price' => '179.99',
                'stock' => 5,
                'imagePath' => null,
            ],
            [
                'id' => 3,
                'name' => 'Orcullo Wireless Arcade Stick',
                'description' => 'Low-latency 2.4GHz wireless arcade fight stick with Sanwa mechanical switches.',
                'price' => '199.99',
                'stock' => 8,
                'imagePath' => null,
            ],
        ];
    }

    public function getUsers(): array
    {
        return [
            [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@orcullo.com',
                'role' => 'admin',
            ],
            [
                'id' => 2,
                'username' => 'player1',
                'email' => 'player1@example.com',
                'role' => 'customer',
            ],
            [
                'id' => 3,
                'username' => 'gamer_pro',
                'email' => 'gamer_pro@example.com',
                'role' => 'customer',
            ],
        ];
    }

    public function getOrders(): array
    {
        $adminUser = ['username' => 'admin'];
        $playerUser = ['username' => 'player1'];

        return [
            [
                'id' => 101,
                'user' => $playerUser,
                'createdAt' => new \DateTime('2026-10-10 14:30:00'),
                'totalAmount' => '249.99',
                'paymentMethod' => 'online',
                'status' => 'paid',
            ],
            [
                'id' => 102,
                'user' => $playerUser,
                'createdAt' => new \DateTime('2026-10-11 09:15:00'),
                'totalAmount' => '179.99',
                'paymentMethod' => 'cod',
                'status' => 'pending',
            ],
            [
                'id' => 103,
                'user' => $adminUser,
                'createdAt' => new \DateTime('2026-10-12 18:00:00'),
                'totalAmount' => '199.99',
                'paymentMethod' => 'online',
                'status' => 'shipped',
            ],
        ];
    }
}
