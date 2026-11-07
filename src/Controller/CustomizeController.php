<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomizeController extends AbstractController
{
    #[Route('/customize', name: 'customize')]
    public function index(): Response
    {
        return $this->render('customize/customize.html.twig');
    }
}
