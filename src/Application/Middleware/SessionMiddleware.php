<?php

declare(strict_types=1);

namespace SlimRack\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SlimRack\Infrastructure\Session\SessionManager;

/**
 * Session Middleware
 *
 * Starts the session for web requests and makes session manager available
 */
class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $session
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Start session if not already started
        $this->session->start();

        // Add session to request attributes for use in handlers
        $request = $request->withAttribute('session', $this->session);

        return $handler->handle($request);
    }
}
