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

        return $this->render('admin/dashboard.html.twig', [
            'total_products' => count($products),
            'total_users' => count($users),
            'total_orders' => count($orders),
            'recent_orders' => array_slice($orders, 0, 5),
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
}