<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Infrastructure\Session\SessionManager;
use SlimRack\Infrastructure\Security\CookieAuth;

/**
 * Logout Action
 *
 * Handles user logout
 */
class LogoutAction
{
    public function __construct(
        private SessionManager $session,
        private CookieAuth $cookieAuth
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        // Clear remember me cookie
        $this->cookieAuth->clearRememberToken();

        // Destroy session
        $this->session->destroy();

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/login');
    }
}
