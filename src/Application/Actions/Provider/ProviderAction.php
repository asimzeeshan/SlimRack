<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Provider;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\Provider\ProviderRepository;

/**
 * Provider Action
 *
 * Handles AJAX requests for provider operations
 */
class ProviderAction
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
            'data' => $providers,
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
            'data' => $provider,
        ]);
    }

    /**
     * Create a new provider
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $errors = $this->validateProviderData($data);

        if (!empty($errors)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        // Check for duplicate name
        if ($this->providerRepo->existsByName($data['name'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => ['name' => 'A provider with this name already exists'],
            ], 422);
        }

        $providerData = $this->prepareProviderData($data);
        $id = $this->providerRepo->create($providerData);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'provider_id' => $id,
                'message' => 'Provider created successfully',
            ],
        ], 201);
    }

    /**
     * Update a provider
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $provider = $this->providerRepo->findById($id);

        if (!$provider) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Provider not found',
            ], 404);
        }

        $data = $request->getParsedBody();
        $errors = $this->validateProviderData($data);

        if (!empty($errors)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        // Check for duplicate name (excluding current provider)
        if ($this->providerRepo->existsByName($data['name'], $id)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => ['name' => 'A provider with this name already exists'],
            ], 422);
        }

        $providerData = $this->prepareProviderData($data);
        $this->providerRepo->update($id, $providerData);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'message' => 'Provider updated successfully',
            ],
        ]);
    }

    /**
     * Delete a provider
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $provider = $this->providerRepo->findById($id);

        if (!$provider) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Provider not found',
            ], 404);
        }

        $this->providerRepo->delete($id);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'message' => 'Provider deleted successfully',
            ],
        ]);
    }

    /**
     * Validate provider data
     */
    private function validateProviderData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Provider name is required';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Provider name is too long (max 255 characters)';
        }

        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Invalid website URL';
        }

        if (!empty($data['control_panel_url']) && !filter_var($data['control_panel_url'], FILTER_VALIDATE_URL)) {
            $errors['control_panel_url'] = 'Invalid control panel URL';
        }

        return $errors;
    }

    /**
     * Prepare provider data for database
     */
    private function prepareProviderData(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'website' => !empty($data['website']) ? trim($data['website']) : null,
            'control_panel_name' => !empty($data['control_panel_name']) ? trim($data['control_panel_name']) : null,
            'control_panel_url' => !empty($data['control_panel_url']) ? trim($data['control_panel_url']) : null,
        ];
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
