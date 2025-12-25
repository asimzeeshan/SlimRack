<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Domain\Country\CountryRepository;

/**
 * Country API Action
 *
 * REST API endpoint for countries
 */
class CountryApiAction
{
    public function __construct(
        private CountryRepository $countryRepo
    ) {}

    /**
     * List all countries
     */
    public function list(Request $request, Response $response): Response
    {
        $countries = $this->countryRepo->findAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'countries' => $countries,
                'total' => count($countries),
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
