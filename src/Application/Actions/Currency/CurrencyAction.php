<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Currency;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\Currency\CurrencyRepository;

/**
 * Currency Action
 *
 * Handles AJAX requests for currency operations
 */
class CurrencyAction
{
    public function __construct(
        private CurrencyRepository $currencyRepo
    ) {}

    /**
     * List all currencies
     */
    public function list(Request $request, Response $response): Response
    {
        $currencies = $this->currencyRepo->findAllWithUsdRates();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $currencies,
        ]);
    }

    /**
     * Create a new currency
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $errors = $this->validateCurrencyData($data);

        if (!empty($errors)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        $code = strtoupper(trim($data['currency_code']));
        $rate = (int) ($data['rate'] * 10000); // Store as integer

        if ($this->currencyRepo->exists($code)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => ['currency_code' => 'This currency already exists'],
            ], 422);
        }

        $this->currencyRepo->create($code, $rate);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'message' => 'Currency created successfully',
            ],
        ], 201);
    }

    /**
     * Update a currency rate
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $code = strtoupper($args['code']);

        if (!$this->currencyRepo->exists($code)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Currency not found',
            ], 404);
        }

        $data = $request->getParsedBody();

        if (!isset($data['rate']) || !is_numeric($data['rate']) || $data['rate'] <= 0) {
            return $this->jsonResponse($response, [
                'success' => false,
                'errors' => ['rate' => 'Rate must be a positive number'],
            ], 422);
        }

        $rate = (int) ($data['rate'] * 10000);
        $this->currencyRepo->update($code, $rate);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'message' => 'Currency rate updated successfully',
            ],
        ]);
    }

    /**
     * Delete a currency
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $code = strtoupper($args['code']);

        if ($code === 'USD') {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Cannot delete base currency (USD)',
            ], 422);
        }

        if (!$this->currencyRepo->exists($code)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Currency not found',
            ], 404);
        }

        $this->currencyRepo->delete($code);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'message' => 'Currency deleted successfully',
            ],
        ]);
    }

    /**
     * Validate currency data
     */
    private function validateCurrencyData(array $data): array
    {
        $errors = [];

        if (empty($data['currency_code'])) {
            $errors['currency_code'] = 'Currency code is required';
        } elseif (!preg_match('/^[A-Z]{3}$/', strtoupper($data['currency_code']))) {
            $errors['currency_code'] = 'Currency code must be 3 uppercase letters';
        }

        if (!isset($data['rate']) || !is_numeric($data['rate']) || $data['rate'] <= 0) {
            $errors['rate'] = 'Rate must be a positive number';
        }

        return $errors;
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
