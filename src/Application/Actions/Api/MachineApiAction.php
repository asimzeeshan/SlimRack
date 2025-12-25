<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\Machine\MachineRepository;

/**
 * Machine API Action
 *
 * REST API endpoints for machines
 */
class MachineApiAction
{
    public function __construct(
        private MachineRepository $machineRepo
    ) {}

    /**
     * List all machines
     */
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $includeHidden = isset($params['include_hidden']) && $params['include_hidden'] === '1';

        $machines = $this->machineRepo->findAllWithDetails($includeHidden);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'machines' => $machines,
                'total' => count($machines),
            ],
        ]);
    }

    /**
     * Get a single machine
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $machine = $this->machineRepo->findByIdWithDetails($id);

        if (!$machine) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Machine not found',
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => ['machine' => $machine],
        ]);
    }

    /**
     * Create a new machine
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $errors = $this->validateMachineData($data);

        if (!empty($errors)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        $machineData = $this->prepareMachineData($data);
        $id = $this->machineRepo->create($machineData);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'message' => 'Machine created successfully',
                'machine_id' => $id,
            ],
        ], 201);
    }

    /**
     * Update a machine
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $machine = $this->machineRepo->findById($id);

        if (!$machine) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Machine not found',
            ], 404);
        }

        $data = $request->getParsedBody() ?? [];
        $errors = $this->validateMachineData($data, true);

        if (!empty($errors)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        $machineData = $this->prepareMachineData($data);
        $this->machineRepo->update($id, $machineData);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => ['message' => 'Machine updated successfully'],
        ]);
    }

    /**
     * Delete a machine
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $machine = $this->machineRepo->findById($id);

        if (!$machine) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Machine not found',
            ], 404);
        }

        $this->machineRepo->delete($id);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => ['message' => 'Machine deleted successfully'],
        ]);
    }

    /**
     * Validate machine data
     */
    private function validateMachineData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (empty($data['label'])) {
            $errors['label'] = 'Label is required';
        }

        $numericFields = ['cpu_speed', 'cpu_core', 'memory', 'swap', 'disk_space', 'bandwidth', 'price'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && (!is_numeric($data[$field]) || $data[$field] < 0)) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a positive number';
            }
        }

        if (!empty($data['country_code']) && !preg_match('/^[A-Z]{2}$/', strtoupper($data['country_code']))) {
            $errors['country_code'] = 'Invalid country code';
        }

        if (!empty($data['currency_code']) && !preg_match('/^[A-Z]{3}$/', strtoupper($data['currency_code']))) {
            $errors['currency_code'] = 'Invalid currency code';
        }

        if (!empty($data['due_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['due_date'])) {
            $errors['due_date'] = 'Invalid date format (use YYYY-MM-DD)';
        }

        return $errors;
    }

    /**
     * Prepare machine data for database
     */
    private function prepareMachineData(array $data): array
    {
        return [
            'label' => $data['label'] ?? '',
            'is_hidden' => isset($data['is_hidden']) ? (int) $data['is_hidden'] : 0,
            'is_nat' => isset($data['is_nat']) ? (int) $data['is_nat'] : 0,
            'virtualization' => $data['virtualization'] ?? null,
            'cpu_speed' => !empty($data['cpu_speed']) ? (int) $data['cpu_speed'] : 0,
            'cpu_core' => !empty($data['cpu_core']) ? (int) $data['cpu_core'] : 0,
            'memory' => !empty($data['memory']) ? (int) $data['memory'] : 0,
            'swap' => !empty($data['swap']) ? (int) $data['swap'] : 0,
            'disk_type' => $data['disk_type'] ?? null,
            'disk_space' => !empty($data['disk_space']) ? (int) $data['disk_space'] : 0,
            'bandwidth' => !empty($data['bandwidth']) ? (int) $data['bandwidth'] : 0,
            'ip_address' => $data['ip_address'] ?? null,
            'country_code' => !empty($data['country_code']) ? strtoupper($data['country_code']) : null,
            'city_name' => $data['city_name'] ?? null,
            'price' => !empty($data['price']) ? (int) $data['price'] : 0,
            'currency_code' => !empty($data['currency_code']) ? strtoupper($data['currency_code']) : 'USD',
            'payment_cycle_id' => !empty($data['payment_cycle_id']) ? (int) $data['payment_cycle_id'] : null,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'provider_id' => !empty($data['provider_id']) ? (int) $data['provider_id'] : null,
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
