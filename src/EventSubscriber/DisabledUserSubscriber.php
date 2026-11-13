<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * If the admin disables an account while its owner is still logged in,
 * the very next request they make ends their session and bounces them to
 * the login page with a notice. (The login check itself is handled by
 * App\Security\UserChecker.)
 */
class DisabledUserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Default priority (0) is fine: the security firewall (8) loads the
        // user before this runs.
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || !$user->isDisabled()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $this->tokenStorage->setToken(null);
        $session->invalidate();
        $session->getFlashBag()->add(
            'admin_error',
            'Your account has been disabled. Please contact an administrator.'
        );

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
    }
}
