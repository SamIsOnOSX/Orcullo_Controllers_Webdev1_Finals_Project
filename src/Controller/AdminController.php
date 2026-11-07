<?php

namespace App\Controller;

use App\Service\MockDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly MockDataService $mockDataService
    ) {
    }

    #[Route('', name: 'admin_dashboard')]
    public function index(): Response
    {
        $products = $this->mockDataService->getProducts();
        $users = $this->mockDataService->getUsers();
        $orders = $this->mockDataService->getOrders();
        $ordersByStatus = $this->countOrdersByStatus($orders);

        return $this->render('admin/dashboard.html.twig', [
            'total_products' => count($products),
            'total_users' => count($users),
            'total_orders' => count($orders),
            'recent_orders' => array_slice($orders, 0, 5),
            'total_revenue' => array_sum(array_column($orders, 'totalAmount')),
            'orders_by_status' => $ordersByStatus,
            'status_max_count' => $ordersByStatus === [] ? 0 : max(array_column($ordersByStatus, 'count')),
            'low_stock_products' => $this->getLowStockProducts($products),
        ]);
    }

    #[Route('/products', name: 'admin_products')]
    public function products(): Response
    {
        return $this->render('admin/products.html.twig', [
            'products' => $this->mockDataService->getProducts(),
        ]);
    }

    #[Route('/users', name: 'admin_users')]
    public function users(): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $this->mockDataService->getUsers(),
        ]);
    }

    #[Route('/orders', name: 'admin_orders')]
    public function orders(): Response
    {
        return $this->render('admin/orders.html.twig', [
            'orders' => $this->mockDataService->getOrders(),
        ]);
    }

    /**
     * Groups orders by status for the dashboard chart.
     *
     * @param array<int, array<string, mixed>> $orders
     * @return list<array{status: string, count: int}>
     */
    private function countOrdersByStatus(array $orders): array
    {
        $counts = [];
        foreach ($orders as $order) {
            $status = strtolower((string) ($order['status'] ?? 'unknown'));
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        arsort($counts);

        $rows = [];
        foreach ($counts as $status => $count) {
            $rows[] = ['status' => $status, 'count' => $count];
        }

        return $rows;
    }

    /**
     * Products at or below the low-stock threshold (out of stock included).
     *
     * @param array<int, array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    private function getLowStockProducts(array $products, int $threshold = 5): array
    {
        return array_values(array_filter(
            $products,
            static fn (array $product): bool => (int) ($product['stock'] ?? 0) <= $threshold
        ));
    }
}