<?php

namespace App\Controller;

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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Staff panel: limited record management. Staff members can browse the
 * shared product catalog, create their own products, and edit or delete
 * ONLY the records they created themselves (products without an owner are
 * admin/shop records). User management and the admin dashboard stay
 * admin-only — /admin/* is blocked for staff by the security.yaml rules.
 */
#[IsGranted('ROLE_STAFF')]
#[Route('/staff')]
final class StaffController extends AbstractController
{
    private const ALLOWED_IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Shared "My Profile" page for admins and staff members. Admins reach
     * it through the role hierarchy (ROLE_ADMIN grants ROLE_STAFF). Shows
     * the account's own details plus activity stats, and lets the user
     * edit their username/email/address or change their own password
     * (which takes effect the next time they sign in).
     */
    #[Route('/profile', name: 'staff_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('staff_profile', (string) $request->request->get('_token'))) {
                $this->addFlash('admin_error', 'Your session expired. Please try again.');
            } elseif ($request->request->get('form_type') === 'password') {
                $this->changeOwnPassword($request, $user);
            } else {
                $this->updateOwnDetails($request, $user);
            }

            return $this->redirectToRoute('staff_profile');
        }

        return $this->render('staff/profile.html.twig', [
            'profile_orders' => $this->orderRepository->countByUser($user),
            'profile_products' => $this->productRepository->countByOwner($user),
        ]);
    }

    /** Saves the signed-in user's own username / email / address. */
    private function updateOwnDetails(Request $request, User $user): void
    {
        $username = trim((string) $request->request->get('username', ''));
        $email = trim((string) $request->request->get('email', ''));
        $address = trim((string) $request->request->get('address', ''));

        if ($username === '' || $email === '') {
            $this->addFlash('admin_error', 'Username and email cannot be empty.');

            return;
        }

        if (mb_strlen($username) < 3) {
            $this->addFlash('admin_error', 'Username must be at least 3 characters.');

            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('admin_error', 'Please enter a valid email address.');

            return;
        }

        $clash = $this->userRepository->findOneBy(['username' => $username]);
        if ($clash !== null && $clash->getId() !== $user->getId()) {
            $this->addFlash('admin_error', "Username '{$username}' is already taken.");

            return;
        }

        $clash = $this->userRepository->findOneBy(['email' => $email]);
        if ($clash !== null && $clash->getId() !== $user->getId()) {
            $this->addFlash('admin_error', "Email '{$email}' is already in use.");

            return;
        }

        $user->setUsername($username);
        $user->setEmail($email);
        $user->setAddress($address !== '' ? $address : null);
        $this->entityManager->flush();
        $this->addFlash('admin_message', 'Your profile has been updated.');
    }

    /** Re-hashes and saves the signed-in user's own password. */
    private function changeOwnPassword(Request $request, User $user): void
    {
        $newPassword = trim((string) $request->request->get('new_password', ''));

        if (strlen($newPassword) < 6) {
            $this->addFlash('admin_error', 'Password must be at least 6 characters.');

            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();
        $this->addFlash('admin_message', 'Your password has been updated. It takes effect the next time you sign in.');
    }

    #[Route('/products', name: 'staff_products')]
    public function products(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        return $this->render('staff/products.html.twig', [
            'products' => $this->productRepository->searchProducts($q),
            'available_assets' => $this->getAvailableAssets(),
            'edit_product' => null,
            'search_q' => $q,
        ]);
    }

    /** Edit form for ONE of the staff member's own records. */
    #[Route('/products/{id}/edit', name: 'staff_edit_product', methods: ['GET'])]
    public function editProduct(int $id): Response
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw new AccessDeniedException('This product record does not exist.');
        }

        $this->assertOwnRecord($product);

        return $this->render('staff/products.html.twig', [
            'products' => $this->productRepository->findAllNewestFirst(),
            'available_assets' => $this->getAvailableAssets(),
            'edit_product' => $product,
        ]);
    }

    #[Route('/products/create', name: 'staff_create_product', methods: ['POST'])]
    public function createProduct(Request $request): RedirectResponse
    {
        [$message, $error] = $this->processProductForm($request, new Product(), true);

        $this->addFlash($error !== null ? 'admin_error' : 'admin_message', $error ?? $message);

        return $this->redirectToRoute('staff_products');
    }

    #[Route('/products/update', name: 'staff_update_product', methods: ['POST'])]
    public function updateProduct(Request $request): RedirectResponse
    {
        $productId = (int) $request->request->get('product_id', 0);
        $product = $this->productRepository->find($productId);

        if ($product === null) {
            $this->addFlash('admin_error', "Failed to update product #$productId.");

            return $this->redirectToRoute('staff_products');
        }

        $this->assertOwnRecord($product);

        [$message, $error] = $this->processProductForm($request, $product, false);

        $this->addFlash($error !== null ? 'admin_error' : 'admin_message', $error ?? $message);

        return $this->redirectToRoute('staff_products');
    }

    /** Staff can only remove records they created themselves. */
    #[Route('/products/delete', name: 'staff_delete_product', methods: ['POST'])]
    public function deleteProduct(Request $request): RedirectResponse
    {
        $productId = (int) $request->request->get('product_id', 0);
        $product = $this->productRepository->find($productId);

        if ($product === null) {
            $this->addFlash('admin_error', "Failed to remove product #$productId.");

            return $this->redirectToRoute('staff_products');
        }

        $this->assertOwnRecord($product);

        $this->entityManager->remove($product);
        $this->entityManager->flush();
        $this->addFlash('admin_message', "Your product '" . $product->getName() . "' was successfully removed.");

        return $this->redirectToRoute('staff_products');
    }

    /**
     * 403 when the record belongs to the shop (admin/seed data) or to
     * another staff member — "edit own records only".
     */
    private function assertOwnRecord(Product $product): void
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$product->isOwnedBy($user)) {
            throw new AccessDeniedException('Staff members can only manage the products they created themselves.');
        }
    }

    /**
     * Shared create/update handling: validates the fields, stores an
     * optional image upload (or an existing asset on create) and saves.
     *
     * @return array{0: string, 1: string|null} [success message, error message]
     */
    private function processProductForm(Request $request, Product $product, bool $isCreate): array
    {
        $name = trim((string) $request->request->get('name', ''));
        $description = trim((string) $request->request->get('description', ''));
        $priceInput = (string) $request->request->get('price', '');
        $stockInput = (string) $request->request->get('stock', '');

        if (!$this->isCsrfTokenValid($isCreate ? 'staff_create_product' : 'staff_update_product', (string) $request->request->get('_token'))) {
            return ['', 'Your session expired. Please try again.'];
        }
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
            $error = $this->saveProductImage($image, $imagePath);
            if ($error !== null) {
                return ['', $error];
            }
            $product->setImagePath($imagePath);
        } elseif ($isCreate && trim((string) $request->request->get('existing_asset', '')) !== '') {
            $product->setImagePath(trim((string) $request->request->get('existing_asset')));
        }

        if ($product->getImagePath() === '') {
            return ['', 'Please provide a product image (upload an image or select an existing asset).'];
        }

        $product->setName($name);
        $product->setDescription($description !== '' ? $description : null);
        $product->setPrice(number_format((float) $priceInput, 2, '.', ''));
        $product->setStock((int) $stockInput);

        if ($isCreate) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $product->setCreatedBy($user);
            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();

        return $isCreate
            ? ["Product '$name' was successfully created!", null]
            : ["Product '$name' was successfully updated.", null];
    }

    /**
     * Validates the upload and stores it under public/uploads/, filling
     * $imagePath with the stored relative path. Returns an error message or null.
     */
    private function saveProductImage(UploadedFile $file, string &$imagePath): ?string
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
     * (same asset library the admin create form offers).
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
                    if (is_file($assetsDir . '/' . $file) && preg_match('/\.(jpe?g|png|webp|gif)$/i', $file)) {
                        $available[] = 'Assets/' . $file;
                    }
                }
            }
        }

        sort($available);

        return $available;
    }
}
