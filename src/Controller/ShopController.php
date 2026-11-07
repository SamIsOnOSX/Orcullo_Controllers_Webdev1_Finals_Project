<?php

namespace App\Controller;

use App\Service\CartService;
use App\Service\MockDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    public function __construct(
        private readonly MockDataService $mockDataService,
        private readonly CartService $cartService,
    ) {
    }

    #[Route('/shop', name: 'shop')]
    public function index(): Response
    {
        return $this->render('shop/shop.html.twig', [
            'products' => $this->mockDataService->getProducts(),
            'cart_items' => $this->cartItemsForPanel(),
            'cart_count' => $this->cartService->count(),
            'cart_total' => $this->cartService->getTotal(),
        ]);
    }

    /**
     * Cart items enriched with product image paths for the summary panel.
     *
     * @return list<array<string, mixed>>
     */
    private function cartItemsForPanel(): array
    {
        $catalog = [];
        foreach ($this->mockDataService->getProducts() as $product) {
            $catalog[(int) ($product['id'] ?? 0)] = $product;
        }

        return array_map(static function (array $item) use ($catalog): array {
            $productId = $item['product_id'] ?? null;
            $item['imagePath'] = ($productId !== null && isset($catalog[$productId]))
                ? $catalog[$productId]['imagePath']
                : null;

            return $item;
        }, $this->cartService->getItems());
    }
}
