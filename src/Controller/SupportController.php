<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of the legacy support/support.php: help cards, FAQ and a contact
 * form (submission acknowledged with a success alert, like the original).
 */
final class SupportController extends AbstractController
{
    #[Route('/support', name: 'support')]
    public function index(Request $request): Response
    {
        $submitted = false;

        if ($request->isMethod('POST') && $request->request->get('send_message') !== null) {
            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $subject = trim((string) $request->request->get('subject', ''));
            $message = trim((string) $request->request->get('message', ''));

            if ($name !== '' && $email !== '' && $subject !== '' && $message !== '') {
                // TODO: plug in mailer (MailerInterface) to actually send the email
                $submitted = true;
            }
        }

        return $this->render('support/support.html.twig', [
            'submitted' => $submitted,
        ]);
    }
}
