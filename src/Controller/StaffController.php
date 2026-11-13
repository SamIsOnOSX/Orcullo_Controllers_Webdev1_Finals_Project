<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly EntityManagerInterface $entityManager,
    ) {
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
