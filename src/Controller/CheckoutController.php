<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Port of the legacy checkout/checkout.php: COD or online (GCash) payment
 * with proof-of-payment upload, FOR UPDATE stock locking, and an atomic
 * order + order_items + stock-deduction transaction.
 */
#[IsGranted('ROLE_USER')]
final class CheckoutController extends AbstractController
{
    private const MAX_PROOF_BYTES = 5 * 1024 * 1024; // 5 MB

    private const ALLOWED_PROOF_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    private const ALLOWED_PROOF_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(
        private readonly CartService $cartService,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($this->cartService->count() === 0) {
            return $this->redirectToRoute('cart');
        }

        // A successful placement flashes the new order id; re-render as the
        // legacy success card view.
        if ($request->getSession()->remove('checkout_success_order_id') !== null) {
            return $this->render('checkout/checkout.html.twig', ['success' => true]);
        }

        return $this->render('checkout/checkout.html.twig', [
            'success' => false,
            'error' => $this->getCheckoutError($request),
            'cart_items' => $this->cartService->getDetailedItems(),
            'cart_total' => $this->cartService->getTotal(),
        ]);
    }

    #[Route('/checkout/confirm', name: 'checkout_confirm', methods: ['POST'])]
    public function confirm(Request $request): RedirectResponse
    {
        if ($this->cartService->count() === 0) {
            return $this->redirectToRoute('cart');
        }

        if (!$this->isCsrfTokenValid('confirm_checkout', (string) $request->request->get('_token'))) {
            return $this->setCheckoutError($request, 'Your session expired. Please try again.');
        }

        $paymentMethod = $request->request->get('payment_method') === 'online'
            ? Order::PAYMENT_ONLINE
            : Order::PAYMENT_COD;
        $paymentProof = null;

        try {
            if ($paymentMethod === Order::PAYMENT_ONLINE) {
                $paymentProof = $this->storeProofOfPayment($request);
            }

            $totalPrice = $this->cartService->getTotal();
            $items = $this->cartService->getItems();

            // Keep the transaction wrapper to ensure order, items and stock
            // deductions save together (legacy behaviour).
            $conn = $this->entityManager->getConnection();
            $conn->beginTransaction();

            try {
                // 1. Stock check with a FOR UPDATE lock.
                foreach ($items as $item) {
                    $productId = $item['product_id'] ?? null;
                    $qty = max(1, (int) ($item['quantity'] ?? 1));

                    if ($productId !== null) {
                        $row = $conn->fetchAssociative(
                            'SELECT id, name, stock FROM products WHERE id = ? FOR UPDATE',
                            [$productId],
                        );

                        if ($row === false) {
                            throw new \RuntimeException(sprintf("Product '%s' is no longer available.", $item['type'] ?? 'Unknown'));
                        }

                        if ((int) $row['stock'] < $qty) {
                            throw new \RuntimeException(sprintf("Insufficient stock for '%s'. Only %d available.", $row['name'], (int) $row['stock']));
                        }
                    }
                }

                // 2. Create the parent order (with payment info).
                $order = new Order();
                $order->setUser($this->getUser());
                $order->setTotalAmount(number_format($totalPrice, 2, '.', ''));
                $order->setPaymentMethod($paymentMethod);
                $order->setPaymentProof($paymentProof);
                $this->entityManager->persist($order);

                // 3. Save one order_item per purchased unit and reduce stock.
                foreach ($items as $item) {
                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $productId = $item['product_id'] ?? null;

                    for ($i = 0; $i < $qty; $i++) {
                        $orderItem = new OrderItem();
                        $orderItem->setOrder($order);
                        $orderItem->setItemType((string) ($item['type'] ?? 'Unknown'));
                        $orderItem->setButtonColor($item['button_color'] ?? 'Standard');
                        $orderItem->setArtworkPath($item['artwork_path'] ?? '');
                        $orderItem->setPrice(number_format((float) ($item['price'] ?? 0), 2, '.', ''));

                        if ($productId !== null) {
                            $orderItem->setProduct($this->entityManager->getReference(Product::class, $productId));
                        }

                        $order->addItem($orderItem);
                        $this->entityManager->persist($orderItem);
                    }

                    if ($productId !== null) {
                        $conn->executeStatement(
                            'UPDATE products SET stock = stock - ? WHERE id = ?',
                            [$qty, $productId],
                        );
                    }
                }

                $this->entityManager->flush();
                $conn->commit();

                // 4. Clear the cart and hand the success view over to GET.
                $this->cartService->clear();
                $request->getSession()->set('checkout_success_order_id', $order->getId());

                return $this->redirectToRoute('checkout');
            } catch (\Throwable $e) {
                if ($conn->isTransactionActive()) {
                    $conn->rollBack();
                }

                throw $e;
            }
        } catch (\Throwable $e) {
            return $this->setCheckoutError($request, $e->getMessage());
        }
    }

    /**
     * Validates and stores the online-payment proof, returning the stored
     * relative path (legacy: uploads/payment_proofs/<time>_<safe>.<ext>).
     */
    private function storeProofOfPayment(Request $request): string
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('payment_proof');

        if ($file === null || !$file->isValid()) {
            throw new \RuntimeException('Please upload your proof of payment for online transactions.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_PROOF_EXTS, true)) {
            throw new \RuntimeException('Invalid file type. Allowed formats: JPG, PNG, WEBP, PDF.');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_PROOF_MIMES, true)) {
            throw new \RuntimeException('Uploaded file is not a valid image or PDF.');
        }

        if ($file->getSize() > self::MAX_PROOF_BYTES) {
            throw new \RuntimeException('Proof of payment file must be under 5 MB.');
        }

        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;

        $directory = $this->getParameter('kernel.project_dir') . '/public/uploads/payment_proofs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $file->move($directory, $safeName);

        return 'uploads/payment_proofs/' . $safeName;
    }

    private function setCheckoutError(Request $request, string $message): RedirectResponse
    {
        $request->getSession()->set('checkout_error', $message);

        return $this->redirectToRoute('checkout');
    }

    private function getCheckoutError(Request $request): ?string
    {
        $error = $request->getSession()->get('checkout_error');
        $request->getSession()->remove('checkout_error');

        return $error !== null ? (string) $error : null;
    }
}
