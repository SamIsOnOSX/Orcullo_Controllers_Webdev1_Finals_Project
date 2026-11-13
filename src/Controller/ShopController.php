<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CartService $cartService,
    ) {
    }

    #[Route('/shop', name: 'shop')]
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        return $this->render('shop/shop.html.twig', [
            'products' => $this->productRepository->searchProducts($q),
            'search_q' => $q,
            'cart_items' => $this->cartItemsForPanel(),
            'cart_count' => $this->cartService->count(),
            'cart_total' => $this->cartService->getTotal(),
        ]);
    }

    /**
     * Cart items enriched with product image paths for the mini-cart.
     *
     * @return list<array<string, mixed>>
     */
    private function cartItemsForPanel(): array
    {
        return array_map(static function (array $item): array {
            $item['imagePath'] = $item['image_path'] ?? null;

            return $item;
        }, $this->cartService->getItems());
    }
}
