<?php

declare(strict_types=1);

namespace SlimRack\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use SlimRack\Infrastructure\Security\CsrfGuard;

/**
 * CSRF Middleware
 *
 * Validates CSRF tokens on state-changing requests (POST, PUT, DELETE, PATCH)
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CsrfGuard $csrf
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = $request->getMethod();

        // Only validate state-changing methods
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $this->getTokenFromRequest($request);

            if (!$this->csrf->validateToken($token)) {
                return $this->createErrorResponse($request);
            }
        }

        // Add CSRF data to request for use in templates
        $request = $request->withAttribute('csrf', [
            'name' => $this->csrf->getTokenName(),
            'value' => $this->csrf->getToken(),
        ]);

        return $handler->handle($request);
    }

    /**
     * Extract CSRF token from request
     */
    private function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $tokenName = $this->csrf->getTokenName();

        // Check header first (for AJAX requests)
        $headerToken = $request->getHeaderLine('X-CSRF-Token');
        if (!empty($headerToken)) {
            return $headerToken;
        }

        // Check body (for form submissions)
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[$tokenName])) {
            return $body[$tokenName];
        }

        // Check query params as fallback
        $query = $request->getQueryParams();
        if (isset($query[$tokenName])) {
            return $query[$tokenName];
        }

        return null;
    }

    /**
     * Create error response for invalid CSRF token
     */
    private function createErrorResponse(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        // Check if AJAX request
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest'
            || str_contains($request->getHeaderLine('Accept'), 'application/json');

        if ($isAjax) {
            $payload = json_encode([
                'success' => false,
                'error' => 'CSRF token validation failed',
                'errors' => [
                    'csrf' => 'Security validation failed. Please refresh the page and try again.',
                ],
            ]);
            $response->getBody()->write($payload);

            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        // For regular requests, return 403 page
        $response->getBody()->write('CSRF token validation failed. Please go back and try again.');

        return $response->withStatus(403);
    }
}
