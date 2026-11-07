<?php

namespace App\Controller;

use App\Service\CartService;
use App\Service\MockDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly MockDataService $mockDataService,
    ) {
    }

    #[Route('/cart', name: 'cart')]
    public function index(): Response
    {
        return $this->render('cart/cart.html.twig', [
            'cart_items' => $this->enrichItems(),
            'cart_total' => $this->cartService->getTotal(),
        ]);
    }

    #[Route('/cart/add', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('add_to_cart', (string) $request->request->get('_token'))) {
            $this->addFlash('cart_error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('shop');
        }

        $productId = $request->request->getInt('product_id', 0);
        $product = $this->findProduct($productId);

        if ($product === null) {
            $this->addFlash('cart_error', 'Sorry, that product is no longer available.');

            return $this->redirectToRoute('shop');
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $error = $this->cartService->addItem(
            $productId,
            $quantity,
            (int) ($product['stock'] ?? 0),
            (string) ($product['name'] ?? 'Product'),
            (float) (string) ($product['price'] ?? 0),
        );

        if ($error !== null) {
            $this->addFlash('cart_error', $error);
        } else {
            $this->addFlash('cart_success', sprintf("'%s' added to your cart.", $product['name']));
        }

        return $this->redirectToRoute('shop');
    }

    #[Route('/cart/update', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('cart_update', (string) $request->request->get('_token'))) {
            $this->addFlash('cart_error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('cart');
        }

        $index = $request->request->getInt('cart_index', -1);
        $items = $this->cartService->getItems();

        if (!isset($items[$index])) {
            return $this->redirectToRoute('cart');
        }

        // The spinner +/- buttons submit an absolute target quantity; the
        // number input submits its own (changed) value.
        $newQuantity = $request->request->has('qty_target')
            ? (int) $request->request->get('qty_target')
            : (int) $request->request->get('quantity', 1);

        $productId = $items[$index]['product_id'] ?? null;
        $product = $productId !== null ? $this->findProduct((int) $productId) : null;

        $error = $this->cartService->updateQuantity($index, $newQuantity, $product);

        if ($error !== null) {
            $this->addFlash('cart_error', $error);
        }

        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/remove', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('cart_remove', (string) $request->request->get('_token'))) {
            $this->addFlash('cart_error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('cart');
        }

        $index = $request->request->getInt('cart_index', -1);
        $this->cartService->removeItem($index);
        $this->addFlash('cart_success', 'Item removed from your cart.');

        return $this->redirectToRoute('cart');
    }

    /**
     * @return list<array<string, mixed>> Cart items with an imagePath added for display.
     */
    private function enrichItems(): array
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

    /**
     * @return array<string, mixed>|null
     */
    private function findProduct(int $productId): ?array
    {
        foreach ($this->mockDataService->getProducts() as $product) {
            if ((int) ($product['id'] ?? 0) === $productId) {
                return $product;
            }
        }

        return null;
    }
}
