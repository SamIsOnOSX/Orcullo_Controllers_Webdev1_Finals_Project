<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Replaces Symfony's default (unstyled) 403 error screen with the site's
 * own dark themed "Access Denied" page. Runs for every AccessDenied
 * exception - the security firewall converts controller-level denials
 * into a 403 HttpException before this picks it up.
 */
class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // 0 runs before TwigBundle's own error renderer (-128), so ours wins.
        return [KernelEvents::EXCEPTION => ['onKernelException', 0]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getResponse() !== null) {
            return;
        }

        $throwable = $event->getThrowable();

        $isDenied = $throwable instanceof AccessDeniedHttpException
            || ($throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() === 403);

        if (!$isDenied) {
            return;
        }

        /** @var \App\Entity\User|null $user */
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof User && $user->isAdmin()) {
            $dashboardUrl = $this->urlGenerator->generate('admin_dashboard');
            $accessHint = 'This area requires additional permissions.';
        } elseif ($user instanceof User && $user->isStaff()) {
            $dashboardUrl = $this->urlGenerator->generate('staff_products');
            $accessHint = 'This area requires an administrator account.';
        } else {
            $dashboardUrl = $this->urlGenerator->generate('homepage');
            $accessHint = 'This area requires a staff or administrator account.';
        }

        $html = $this->twig->render('error/403.html.twig', [
            'username' => $user?->getUserIdentifier(),
            'dashboard_url' => $dashboardUrl,
            'access_hint' => $accessHint,
        ]);

        $event->setResponse(new Response($html, 403));
    }
}
