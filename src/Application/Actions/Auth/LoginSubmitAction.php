<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Infrastructure\Session\SessionManager;
use SlimRack\Infrastructure\Security\CsrfGuard;
use SlimRack\Infrastructure\Security\CookieAuth;

/**
 * Login Submit Action
 *
 * Handles login form submission
 */
class LoginSubmitAction
{
    public function __construct(
        private SessionManager $session,
        private CsrfGuard $csrf,
        private CookieAuth $cookieAuth,
        private array $authSettings
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Validate CSRF
        $csrfToken = $body[$this->csrf->getTokenName()] ?? '';
        if (!$this->csrf->validateToken($csrfToken)) {
            return $this->redirectWithError($response, 'Security validation failed. Please try again.');
        }

        // Get credentials
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $remember = isset($body['remember']);

        // Validate input
        if (empty($username) || empty($password)) {
            return $this->redirectWithError($response, 'Please enter username and password.');
        }

        // Validate username format (6-20 alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $username)) {
            return $this->redirectWithError($response, 'Invalid username format.');
        }

        // Check credentials
        $validUsername = $this->authSettings['username'] ?? '';
        $passwordHash = $this->authSettings['passwordHash'] ?? '';

        if ($username !== $validUsername) {
            // Use constant-time comparison to prevent timing attacks
            password_verify($password, '$2y$10$dummy.hash.to.prevent.timing.attacks');
            return $this->redirectWithError($response, 'Invalid username or password.');
        }

        if (!password_verify($password, $passwordHash)) {
            return $this->redirectWithError($response, 'Invalid username or password.');
        }

        // Authentication successful
        $this->session->regenerate();
        $this->session->set('authenticated', true);
        $this->session->set('username', $username);

        // Set remember me cookie if requested
        if ($remember) {
            $this->cookieAuth->createRememberToken($username, $passwordHash);
        }

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/');
    }

    private function redirectWithError(Response $response, string $message): Response
    {
        $this->session->flash('error', $message);

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/login');
    }
}
