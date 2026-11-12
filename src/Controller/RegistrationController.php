<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of the legacy auth/register.php: username + email + password
 * (min 6 chars), duplicate account check, silent redirect to login.
 */
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('homepage');
        }

        $errors = [];
        $username = '';
        $email = '';

        if ($request->isMethod('POST')) {
            $username = trim((string) $request->request->get('username', ''));
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');

            if ($username === '') {
                $errors[] = 'Username is required.';
            }
            if ($email === '') {
                $errors[] = 'Email is required.';
            } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Enter a valid email address.';
            }
            if ($password === '') {
                $errors[] = 'Password is required.';
            } elseif (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }

            if ($errors === []) {
                if ($this->userRepository->existsByUsernameOrEmailExcluding($username, $email)) {
                    $errors[] = 'This email or username is already registered.';
                } else {
                    $user = new User();
                    $user->setUsername($username);
                    $user->setEmail($email);
                    $user->setPassword($this->passwordHasher->hashPassword($user, $password));

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'errors' => $errors,
            'username' => $username,
            'email' => $email,
        ]);
    }
}
