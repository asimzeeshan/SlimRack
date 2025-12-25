<?php

declare(strict_types=1);

namespace SlimRack\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use SlimRack\Infrastructure\Session\SessionManager;
use SlimRack\Infrastructure\Security\CookieAuth;

/**
 * Auth Middleware
 *
 * Protects routes by requiring authentication
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $session,
        private CookieAuth $cookieAuth,
        private array $authSettings
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Check if user is authenticated via session
        if ($this->session->get('authenticated') === true) {
            return $handler->handle($request);
        }

        // Try to authenticate via "Remember Me" cookie
        if ($this->cookieAuth->hasRememberToken()) {
            $passwordHash = $this->authSettings['passwordHash'] ?? '';
            $username = $this->cookieAuth->validateRememberToken($passwordHash);

            if ($username !== null && $username === $this->authSettings['username']) {
                // Valid remember token - create session
                $this->session->set('authenticated', true);
                $this->session->set('username', $username);
                $this->session->regenerate();

                return $handler->handle($request);
            }
        }

        // Not authenticated - check if this is an AJAX request
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest'
            || str_contains($request->getHeaderLine('Accept'), 'application/json');

        if ($isAjax) {
            // Return JSON error for AJAX requests
            $response = new Response();
            $payload = json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'redirect' => '/login',
            ]);
            $response->getBody()->write($payload);

            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // Redirect to login page for regular requests
        $response = new Response();
        return $response
            ->withStatus(302)
            ->withHeader('Location', '/login');
    }
}
