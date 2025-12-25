<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\Machine\MachineRepository;

/**
 * Stats API Action
 *
 * REST API endpoint for statistics
 */
class StatsApiAction
{
    public function __construct(
        private MachineRepository $machineRepo
    ) {}

    /**
     * Get statistics
     */
    public function get(Request $request, Response $response): Response
    {
        $stats = $this->machineRepo->getStatistics();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => ['stats' => $stats],
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
