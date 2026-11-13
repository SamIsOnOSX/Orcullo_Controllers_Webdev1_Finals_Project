<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Port of the legacy auth/login.php flow: sign in with either the
 * username or the email address, keep the legacy error message, and
 * land on the storefront after a successful login.
 */
final class LoginFormAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->attributes->get('_route') === self::LOGIN_ROUTE
            && $request->request->has('identifier');
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('identifier', ''));
        $password = (string) $request->request->get('password', '');

        if ($identifier === '' || $password === '') {
            throw new CustomUserMessageAuthenticationException('Invalid username/email or password.');
        }

        $user = $this->userRepository->findByUsernameOrEmail($identifier);

        if ($user === null) {
            throw new CustomUserMessageAuthenticationException('Invalid username/email or password.');
        }

        return new Passport(
            new UserBadge($identifier, static fn (): \App\Entity\User => $user),
            new PasswordCredentials($password),
            [new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token'))],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var \App\Entity\User $user */
        $user = $token->getUser();

        // Role-based landing: admins open the admin dashboard, staff their
        // own panel, customers stay on the storefront.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        if (in_array('ROLE_STAFF', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('staff_products'));
        }

        return new RedirectResponse($this->urlGenerator->generate('homepage'));
    }

    /**
     * Entry point for anonymous visitors hitting a protected page: send
     * them to the login form instead of a bare 401.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Keep custom messages (empty fields / unknown identifier); coerce
        // everything else (bad password, CSRF) to the legacy wording.
        $message = $exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessage()
            : 'Invalid username/email or password.';

        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new CustomUserMessageAuthenticationException($message));
        }

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    public function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
