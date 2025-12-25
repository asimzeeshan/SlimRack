<?php

declare(strict_types=1);

namespace SlimRack\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * API Key Middleware
 *
 * Validates API key for REST API access
 */
class ApiKeyMiddleware implements MiddlewareInterface
{
    private array $validApiKeys;

    public function __construct(array $apiSettings)
    {
        $this->validApiKeys = $apiSettings['keys'] ?? [];
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Handle CORS preflight
        if ($request->getMethod() === 'OPTIONS') {
            return $this->createCorsResponse();
        }

        // Validate API key
        $apiKey = $this->getApiKeyFromRequest($request);

        if (!$this->isValidApiKey($apiKey)) {
            return $this->createUnauthorizedResponse();
        }

        // Add CORS headers to response
        $response = $handler->handle($request);

        return $this->addCorsHeaders($response);
    }

    /**
     * Extract API key from request
     */
    private function getApiKeyFromRequest(ServerRequestInterface $request): ?string
    {
        // Check X-API-Key header
        $headerKey = $request->getHeaderLine('X-API-Key');
        if (!empty($headerKey)) {
            return $headerKey;
        }

        // Check Authorization header (Bearer token)
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check query parameter as fallback
        $query = $request->getQueryParams();
        if (isset($query['api_key'])) {
            return $query['api_key'];
        }

        return null;
    }

    /**
     * Check if API key is valid
     */
    private function isValidApiKey(?string $apiKey): bool
    {
        if ($apiKey === null || $apiKey === '') {
            return false;
        }

        // If no API keys are configured, deny all access
        if (empty($this->validApiKeys)) {
            return false;
        }

        return in_array($apiKey, $this->validApiKeys, true);
    }

    /**
     * Create unauthorized response
     */
    private function createUnauthorizedResponse(): ResponseInterface
    {
        $response = new Response();

        $payload = json_encode([
            'success' => false,
            'error' => 'Invalid or missing API key',
            'message' => 'Please provide a valid API key via X-API-Key header',
        ]);

        $response->getBody()->write($payload);

        return $this->addCorsHeaders(
            $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json')
        );
    }

    /**
     * Create CORS preflight response
     */
    private function createCorsResponse(): ResponseInterface
    {
        $response = new Response();
        return $this->addCorsHeaders($response->withStatus(204));
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
