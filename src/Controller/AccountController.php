<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Port of the legacy dashboard/user.php: profile update, password change
 * and the customer's order history.
 */
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/account', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $message = '';
        $error = '';

        if ($request->isMethod('POST')) {
            $action = $request->request->get('update_profile') !== null ? 'update_profile' : 'change_password';

            if (!$this->isCsrfTokenValid($action, (string) $request->request->get('_token', ''))) {
                $error = 'Your session expired. Please try again.';
            } elseif ($action === 'update_profile') {
                [$message, $error] = $this->updateProfile($request, $user);            } else {
                [$message, $error] = $this->changePassword($request, $user);
            }
        }

        return $this->render('account/account.html.twig', [
            'user' => $user,
            'orders' => $this->orderRepository->findByUserNewestFirst($user),
            'message' => $message,
            'error' => $error,
        ]);
    }

    /**
     * @return array{0: string, 1: string} [message, error]
     */
    private function updateProfile(Request $request, \App\Entity\User $user): array
    {
        $newUsername = trim((string) $request->request->get('username', ''));
        $newEmail = trim((string) $request->request->get('email', ''));
        $newAddress = trim((string) $request->request->get('address', ''));

        if ($newUsername === '' || $newEmail === '') {
            return ['', 'Username and email cannot be blank.'];
        }

        if ($this->userRepository->existsByUsernameOrEmailExcluding($newUsername, $newEmail, $user->getId())) {
            return ['', 'That username or email is already in use by another account.'];
        }

        $user->setUsername($newUsername);
        $user->setEmail($newEmail);
        $user->setAddress($newAddress !== '' ? $newAddress : null);
        $this->entityManager->flush();

        return ['Profile successfully updated.', ''];
    }

    /**
     * @return array{0: string, 1: string} [message, error]
     */
    private function changePassword(Request $request, \App\Entity\User $user): array
    {
        $currentPwd = (string) $request->request->get('current_password', '');
        $newPwd = (string) $request->request->get('new_password', '');
        $confirmPwd = (string) $request->request->get('confirm_password', '');

        if ($currentPwd === '' || $newPwd === '' || $confirmPwd === '') {
            return ['', 'All password fields are required.'];
        }
        if (strlen($newPwd) < 6) {
            return ['', 'New password must be at least 6 characters long.'];
        }
        if ($newPwd !== $confirmPwd) {
            return ['', 'New password and confirmation do not match.'];
        }
        if (!$this->passwordHasher->isPasswordValid($user, $currentPwd)) {
            return ['', 'Incorrect current password.'];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPwd));
        $this->entityManager->flush();

        return ['Your password has been successfully changed.', ''];
    }
}
