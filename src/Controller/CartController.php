<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ProductRepository $productRepository,
    ) {
    }

    #[Route('/cart', name: 'cart')]
    public function index(): Response
    {
        return $this->render('cart/cart.html.twig', [
            'cart_items' => $this->cartService->getDetailedItems(),
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
        $product = $this->productRepository->find($productId);

        if ($product === null) {
            $this->addFlash('cart_error', 'Sorry, that product is no longer available.');

            return $this->redirectToRoute('shop');
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $error = $this->cartService->addItem(
            $productId,
            $quantity,
            $product->getStock(),
            $product->getName(),
            (float) $product->getPrice(),
        );

        if ($error !== null) {
            $this->addFlash('cart_error', $error);
        } else {
            $this->addFlash('cart_success', sprintf("'%s' added to your cart.", $product->getName()));
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
        $product = is_int($productId) ? $this->productRepository->find($productId) : null;

        $error = $this->cartService->updateQuantity($index, $newQuantity, $product !== null ? $this->productToArray($product) : null);

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
     * Legacy CartService::updateQuantity caps against an array product shape.
     *
     * @return array{name: string, stock: int}
     */
    private function productToArray(Product $product): array
    {
        return ['name' => $product->getName(), 'stock' => $product->getStock()];
    }
}
