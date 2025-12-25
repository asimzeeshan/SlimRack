<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Machine;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\Machine\MachineRepository;

/**
 * Machine Action
 *
 * Handles AJAX requests for machine operations
 */
class MachineAction
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
            'data' => $machines,
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
            'data' => $machine,
        ]);
    }

    /**
     * Create a new machine
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
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
                'machine_id' => $id,
                'message' => 'Machine created successfully',
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

        $data = $request->getParsedBody();
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
            'data' => [
                'message' => 'Machine updated successfully',
            ],
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
            'data' => [
                'message' => 'Machine deleted successfully',
            ],
        ]);
    }

    /**
     * Batch delete machines
     */
    public function batchDelete(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'No machines selected',
            ], 422);
        }

        $ids = array_map('intval', $ids);
        $deleted = $this->machineRepo->deleteMany($ids);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'deleted' => $deleted,
                'message' => "{$deleted} machine(s) deleted successfully",
            ],
        ]);
    }

    /**
     * Toggle machine hidden status
     */
    public function toggleHidden(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        if (!$this->machineRepo->toggleHidden($id)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Machine not found',
            ], 404);
        }

        $machine = $this->machineRepo->findById($id);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'is_hidden' => (bool) $machine['is_hidden'],
                'message' => $machine['is_hidden'] ? 'Machine hidden' : 'Machine visible',
            ],
        ]);
    }

    /**
     * Renew machine due date
     */
    public function renew(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $newDueDate = $this->machineRepo->renewDueDate($id);

        if (!$newDueDate) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Cannot renew machine. Check due date and payment cycle.',
            ], 422);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'due_date' => $newDueDate,
                'message' => 'Due date renewed successfully',
            ],
        ]);
    }

    /**
     * Get cities for autocomplete
     */
    public function cities(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $cities = $this->machineRepo->getDistinctCities($query);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $cities,
        ]);
    }

    /**
     * Validate machine data
     */
    private function validateMachineData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // Required fields
        if (empty($data['label'])) {
            $errors['label'] = 'Label is required';
        }

        // Numeric validations
        $numericFields = ['cpu_speed', 'cpu_core', 'memory', 'swap', 'disk_space', 'bandwidth', 'price'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && (!is_numeric($data[$field]) || $data[$field] < 0)) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a positive number';
            }
        }

        // Country code validation
        if (!empty($data['country_code']) && !preg_match('/^[A-Z]{2}$/', strtoupper($data['country_code']))) {
            $errors['country_code'] = 'Invalid country code';
        }

        // Currency code validation
        if (!empty($data['currency_code']) && !preg_match('/^[A-Z]{3}$/', strtoupper($data['currency_code']))) {
            $errors['currency_code'] = 'Invalid currency code';
        }

        // Date validation
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
            'is_hidden' => isset($data['is_hidden']) ? 1 : 0,
            'is_nat' => isset($data['is_nat']) ? 1 : 0,
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
            'price' => !empty($data['price']) ? (int) ($data['price'] * 100) : 0, // Store as cents
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
