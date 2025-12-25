<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\PaymentCycle\PaymentCycleRepository;

/**
 * PaymentCycle API Action
 *
 * REST API endpoint for payment cycles
 */
class PaymentCycleApiAction
{
    public function __construct(
        private PaymentCycleRepository $paymentCycleRepo
    ) {}

    /**
     * List all payment cycles
     */
    public function list(Request $request, Response $response): Response
    {
        $paymentCycles = $this->paymentCycleRepo->findAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'payment_cycles' => $paymentCycles,
                'total' => count($paymentCycles),
            ],
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
