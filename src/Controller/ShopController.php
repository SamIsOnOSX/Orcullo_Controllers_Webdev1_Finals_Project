<?php

namespace App\Controller;

use App\Service\MockDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    public function __construct(
        private readonly MockDataService $mockDataService
    ) {
    }

    #[Route('/shop', name: 'shop')]
    public function index(): Response
    {
        return $this->render('shop/shop.html.twig', [
            'products' => $this->mockDataService->getProducts(),
        ]);
    }
}
