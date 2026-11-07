<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Session-backed shopping cart that mirrors the legacy cart.php behaviour:
 * items are stored as snapshots (type, button_color, price, artwork_path) with
 * quantities capped against available stock.
 */
final class CartService
{
    private const SESSION_KEY = 'cart';

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        return array_values((array) $this->session()->get(self::SESSION_KEY, []));
    }

    /**
     * Adds a product item, merging with an existing line and capping quantity to stock.
     * Returns an error message, or null on success.
     */
    public function addItem(int $productId, int $quantity, int $availableStock, string $name, float $price): ?string
    {
        if ($availableStock <= 0) {
            return sprintf("Sorry, '%s' is out of stock.", $name);
        }

        $items = (array) $this->session()->get(self::SESSION_KEY, []);
        $quantity = min($quantity, $availableStock);

        foreach ($items as $index => $existingItem) {
            if (($existingItem['product_id'] ?? null) === $productId) {
                $newQuantity = ($existingItem['quantity'] ?? 1) + $quantity;
                $error = null;

                if ($newQuantity > $availableStock) {
                    $newQuantity = $availableStock;
                    $error = sprintf("Limited to maximum available stock (%d) for '%s'.", $availableStock, $name);
                }

                $items[$index]['quantity'] = $newQuantity;
                $this->session()->set(self::SESSION_KEY, $items);

                return $error;
            }
        }

        $items[] = [
            'product_id' => $productId,
            'type' => $name,
            'button_color' => 'Standard',
            'price' => $price,
            'quantity' => $quantity,
            'artwork_path' => '',
        ];
        $this->session()->set(self::SESSION_KEY, $items);

        return null;
    }

    /**
     * Updates the quantity of a cart line (removes it when <= 0). Caps the
     * quantity to the product's stock when a product array is provided.
     * Returns an error message, or null on success.
     *
     * @param array<string, mixed>|null $product
     */
    public function updateQuantity(int $index, int $quantity, ?array $product = null): ?string
    {
        $items = (array) $this->session()->get(self::SESSION_KEY, []);

        if (!isset($items[$index])) {
            return null;
        }

        if ($quantity <= 0) {
            unset($items[$index]);
            $this->session()->set(self::SESSION_KEY, array_values($items));

            return null;
        }

        $error = null;
        if ($product !== null) {
            $availableStock = (int) ($product['stock'] ?? 0);

            if ($quantity > $availableStock) {
                $quantity = $availableStock;
                $error = sprintf("Maximum available stock for '%s' is %d.", $product['name'], $availableStock);
            }
        }

        $items[$index]['quantity'] = max(1, $quantity);
        $this->session()->set(self::SESSION_KEY, array_values($items));

        return $error;
    }

    public function removeItem(int $index): void
    {
        $items = (array) $this->session()->get(self::SESSION_KEY, []);

        if (isset($items[$index])) {
            unset($items[$index]);
            $this->session()->set(self::SESSION_KEY, array_values($items));
        }
    }

    public function getTotal(): float
    {
        $total = 0.0;

        foreach ($this->getItems() as $item) {
            $total += (float) ($item['price'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
        }

        return $total;
    }

    /** Total number of units in the cart (for badges). */
    public function count(): int
    {
        $count = 0;

        foreach ($this->getItems() as $item) {
            $count += max(1, (int) ($item['quantity'] ?? 1));
        }

        return $count;
    }

    private function session(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
