<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Port of the legacy dashboard/admin_actions.php: product creation with
 * image upload (or existing asset), stock updates, product deletion, user
 * management, order status updates and customer password resets.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
final class AdminController extends AbstractController
{
    private const ALLOWED_IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $ordersByStatus = $this->orderRepository->countByStatus();

        return $this->render('admin/dashboard.html.twig', [
            'total_products' => $this->productRepository->count([]),
            'total_users' => $this->userRepository->count([]),
            'total_orders' => $this->orderRepository->countAll(),
            'total_revenue' => $this->orderRepository->sumTotalAmount(),
            'recent_orders' => array_slice($this->orderRepository->findAllNewestFirst(), 0, 5),
            'orders_by_status' => $ordersByStatus,
            'status_max_count' => $ordersByStatus === [] ? 0 : max(array_column($ordersByStatus, 'count')),
            'low_stock_products' => $this->productRepository->findLowStock(),
        ]);
    }

    #[Route('/products', name: 'admin_products')]
    public function products(): Response
    {
        return $this->render('admin/products.html.twig', [
            'products' => $this->productRepository->findAllNewestFirst(),
            'available_assets' => $this->getAvailableAssets(),
        ]);
    }

    #[Route('/users', name: 'admin_users')]
    public function users(): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $this->userRepository->findAllNewestFirst(),
        ]);
    }

    #[Route('/orders', name: 'admin_orders')]
    public function orders(): Response
    {
        return $this->render('admin/orders.html.twig', [
            'orders' => $this->orderRepository->findAllNewestFirst(),
        ]);
    }

    /* ----------------------------------------------------------------
     * Admin POST actions (legacy admin_actions.php)
     * ---------------------------------------------------------------- */

    #[Route('/products/create', name: 'admin_create_product', methods: ['POST'])]
    public function createProduct(Request $request): RedirectResponse
    {
        [$message, $error] = $this->processCreateProduct($request);
        $flashKey = $error !== null ? 'admin_error' : 'admin_message';

        $this->addFlash($flashKey, $error ?? $message);

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/products/delete', name: 'admin_delete_product', methods: ['POST'])]
    public function deleteProduct(Request $request): RedirectResponse
    {
        $productId = (int) $request->request->get('product_id', 0);

        if ($productId > 0 && !$this->isCsrfTokenValid('admin_delete_product', (string) $request->request->get('_token'))) {
            $error = 'Your session expired. Please try again.';
        } elseif ($productId <= 0) {
            $error = "Failed to remove product #$productId.";
        } else {
            $product = $this->productRepository->find($productId);
            $error = $product === null
                ? "Failed to remove product #$productId."
                : null;

            if ($error === null) {
                $this->entityManager->remove($product);
                $this->entityManager->flush();
                $this->addFlash('admin_message', "Product #$productId was successfully removed.");

                return $this->redirectToRoute('admin_products');
            }
        }

        $this->addFlash('admin_error', $error);

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/products/stock', name: 'admin_update_stock', methods: ['POST'])]
    public function updateStock(Request $request): RedirectResponse
    {
        $productId = (int) $request->request->get('product_id', 0);
        $newStock = (int) $request->request->get('new_stock', 0);

        if (!$this->isCsrfTokenValid('admin_update_stock', (string) $request->request->get('_token'))) {
            $error = 'Your session expired. Please try again.';
        } elseif ($productId <= 0) {
            $error = 'Failed to update product stock.';
        } else {
            $product = $this->productRepository->find($productId);
            $error = $product === null ? 'Failed to update product stock.' : null;

            if ($error === null) {
                $product->setStock(max(0, $newStock));
                $this->entityManager->flush();
                $this->addFlash('admin_message', 'Product stock successfully updated!');

                return $this->redirectToRoute('admin_products');
            }
        }

        $this->addFlash('admin_error', $error);

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/users/update', name: 'admin_update_user', methods: ['POST'])]
    public function updateUser(Request $request): RedirectResponse
    {
        $targetId = (int) $request->request->get('user_id', 0);
        $username = trim((string) $request->request->get('username', ''));
        $email = trim((string) $request->request->get('email', ''));
        $role = strtolower(trim((string) $request->request->get('role', 'customer')));

        // Map legacy 'user' to the database 'customer' role.
        if ($role === 'user') {
            $role = User::ROLE_CUSTOMER;
        }

        if (!$this->isCsrfTokenValid('admin_update_user', (string) $request->request->get('_token'))) {
            $error = 'Your session expired. Please try again.';
        } elseif (!in_array($role, [User::ROLE_CUSTOMER, User::ROLE_ADMIN], true)) {
            $error = 'Invalid role selected.';
        } elseif ($targetId <= 0 || $username === '' || $email === '') {
            $error = 'Username and email cannot be empty.';
        } else {
            $target = $this->userRepository->find($targetId);
            $error = $target === null ? "Failed to update user #$targetId." : null;

            if ($error === null) {
                $target->setUsername($username);
                $target->setEmail($email);
                $target->setRole($role);
                $this->entityManager->flush();
                $this->addFlash('admin_message', "User #$targetId successfully updated to " . ucfirst($role) . '.');

                return $this->redirectToRoute('admin_users');
            }
        }

        $this->addFlash('admin_error', $error);

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/orders/status', name: 'admin_update_order', methods: ['POST'])]
    public function updateOrderStatus(Request $request): RedirectResponse
    {
        $orderId = (int) $request->request->get('order_id', 0);
        $status = strtolower(trim((string) $request->request->get('status', 'pending')));

        if (!$this->isCsrfTokenValid('admin_update_order', (string) $request->request->get('_token'))) {
            $error = 'Your session expired. Please try again.';
        } elseif (!in_array($status, Order::ALLOWED_STATUSES, true)) {
            $error = 'Invalid order status selected.';
        } elseif ($orderId <= 0) {
            $error = 'Invalid order ID.';
        } else {
            $order = $this->orderRepository->find($orderId);
            $error = $order === null ? "Failed to update order #$orderId." : null;

            if ($error === null) {
                $order->setStatus($status);
                $this->entityManager->flush();
                $this->addFlash('admin_message', "Order #$orderId status changed to '" . ucfirst($status) . "'.");

                return $this->redirectToRoute('admin_orders');
            }
        }

        $this->addFlash('admin_error', $error);

        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/users/password', name: 'admin_change_password', methods: ['POST'])]
    public function changeUserPassword(Request $request): RedirectResponse
    {
        $targetId = (int) $request->request->get('user_id', 0);
        $newPassword = trim((string) $request->request->get('new_password', ''));

        if (!$this->isCsrfTokenValid('admin_change_password', (string) $request->request->get('_token'))) {
            $error = 'Your session expired. Please try again.';
        } elseif ($targetId <= 0) {
            $error = 'Invalid user ID.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $target = $this->userRepository->find($targetId);

            if ($target === null) {
                $error = "User #$targetId not found.";
            } elseif ($target->getRole() === User::ROLE_ADMIN) {
                $error = "You cannot change another admin's password.";
            } else {
                $target->setPassword($this->passwordHasher->hashPassword($target, $newPassword));
                $this->entityManager->flush();
                $this->addFlash('admin_message', "Password for User #$targetId has been updated.");

                return $this->redirectToRoute('admin_users');
            }
        }

        $this->addFlash('admin_error', $error);

        return $this->redirectToRoute('admin_users');
    }

    /* ----------------------------------------------------------------
     * Helpers (legacy processCreateProduct / saveUploadedProductImage)
     * ---------------------------------------------------------------- */

    /**
     * @return array{0: string, 1: string|null} [success message, error message]
     */
    private function processCreateProduct(Request $request): array
    {
        $name = trim((string) $request->request->get('name', ''));
        $description = trim((string) $request->request->get('description', ''));
        $priceInput = (string) $request->request->get('price', '');
        $stockInput = (string) $request->request->get('stock', '');

        if ($name === '') {
            return ['', 'Product name is required.'];
        }
        if (!is_numeric($priceInput) || (float) $priceInput < 0) {
            return ['', 'Please enter a valid price (0 or higher).'];
        }
        if (!is_numeric($stockInput) || (int) $stockInput < 0) {
            return ['', 'Please enter a valid stock quantity (0 or higher).'];
        }

        /** @var UploadedFile|null $image */
        $image = $request->files->get('product_image');
        $imagePath = '';

        if ($image !== null && $image->isValid()) {
            $error = $this->saveUploadedProductImage($image, $imagePath);
            if ($error !== null) {
                return ['', $error];
            }
        } elseif (trim((string) $request->request->get('existing_asset', '')) !== '') {
            $imagePath = trim((string) $request->request->get('existing_asset'));
        }

        if ($imagePath === '') {
            return ['', 'Please provide a product image (upload an image or select an existing asset).'];
        }

        $product = new Product();
        $product->setName($name);
        $product->setDescription($description !== '' ? $description : null);
        $product->setPrice(number_format((float) $priceInput, 2, '.', ''));
        $product->setStock((int) $stockInput);
        $product->setImagePath($imagePath);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return ["Product '$name' was successfully created!", null];
    }

    /**
     * Validates the upload and stores it under public/uploads/, filling
     * $imagePath with the stored relative path. Returns an error message or null.
     */
    private function saveUploadedProductImage(UploadedFile $file, string &$imagePath): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_IMAGE_EXTS, true)) {
            return 'Invalid file type. Allowed formats: JPG, PNG, WEBP, GIF.';
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_IMAGE_MIMES, true)) {
            return 'Uploaded file is not a valid image.';
        }

        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;

        $directory = $this->getParameter('kernel.project_dir') . '/public/uploads';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        try {
            $file->move($directory, $safeName);
        } catch (\Throwable) {
            return 'Failed to save uploaded image.';
        }

        $imagePath = 'uploads/' . $safeName;

        return null;
    }

    /**
     * Scans the public Assets directory and returns image paths
     * (legacy getAvailableAssets()).
     *
     * @return list<string>
     */
    private function getAvailableAssets(): array
    {
        $assetsDir = $this->getParameter('kernel.project_dir') . '/public/Assets';
        $available = [];

        if (is_dir($assetsDir)) {
            $files = scandir($assetsDir);
            if ($files !== false) {
                foreach ($files as $file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
                        $available[] = 'Assets/' . $file;
                    }
                }
            }
        }

        return $available;
    }
}
