<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\Provider\ProviderRepository;

/**
 * Provider API Action
 *
 * REST API endpoints for providers
 */
class ProviderApiAction
{
    public function __construct(
        private ProviderRepository $providerRepo
    ) {}

    /**
     * List all providers
     */
    public function list(Request $request, Response $response): Response
    {
        $providers = $this->providerRepo->findAllWithMachineCount();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'providers' => $providers,
                'total' => count($providers),
            ],
        ]);
    }

    /**
     * Get a single provider
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $provider = $this->providerRepo->findByIdWithMachineCount($id);

        if (!$provider) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Provider not found',
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => ['provider' => $provider],
        ]);
    }

    /**
     * Create JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
