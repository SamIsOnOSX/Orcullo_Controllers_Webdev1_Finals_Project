<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomizeController extends AbstractController
{
    private const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 10 * 1024 * 1024; // 10MB for artwork

    private const CONTROLLER_PRICES = [
        'arcade_stick' => ['name' => 'Arcade Stick', 'price' => 150.00],
        'leverless' => ['name' => 'Leverless', 'price' => 160.00],
        'gamepad' => ['name' => 'Gamepad', 'price' => 120.00],
    ];

    public function __construct(
        private readonly CartService $cartService,
    ) {
    }

    #[Route('/customize', name: 'customize', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('customize_controller', (string) $request->request->get('_token'))) {
                $this->addFlash('customize_error', 'Your session expired. Please try again.');
                return $this->redirectToRoute('customize');
            }

            $typeKey = (string) $request->request->get('type', 'arcade_stick');
            $config = self::CONTROLLER_PRICES[$typeKey] ?? self::CONTROLLER_PRICES['arcade_stick'];
            $buttonColor = (string) $request->request->get('button_color', 'black');

            $artworkPath = '';
            /** @var UploadedFile|null $file */
            $file = $request->files->get('custom_artwork');

            if ($file instanceof UploadedFile && $file->isValid()) {
                $validationError = $this->validateArtworkFile($file);
                if ($validationError !== null) {
                    $this->addFlash('customize_error', $validationError);
                    return $this->redirectToRoute('customize');
                }

                $artworkPath = $this->storeArtworkFile($file);
            }

            $this->cartService->addCustomItem(
                type: $config['name'],
                buttonColor: ucfirst($buttonColor),
                price: $config['price'],
                artworkPath: $artworkPath,
            );

            $this->addFlash('customize_success', sprintf("Custom %s added to your cart!", $config['name']));
            return $this->redirectToRoute('cart');
        }

        return $this->render('customize/customize.html.twig');
    }

    private function validateArtworkFile(UploadedFile $file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTS, true)) {
            return 'Invalid file type. Allowed image formats: JPG, PNG, WEBP.';
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            return 'Uploaded file is not a valid image.';
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return 'Artwork file must be under 10 MB.';
        }

        return null;
    }

    private function storeArtworkFile(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;

        $directory = $this->getParameter('kernel.project_dir') . '/public/uploads';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $file->move($directory, $safeName);

        return 'uploads/' . $safeName;
    }
}
