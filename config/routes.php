<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use SlimRack\Application\Actions\HomeAction;
use SlimRack\Application\Actions\Auth\LoginAction;
use SlimRack\Application\Actions\Auth\LoginSubmitAction;
use SlimRack\Application\Actions\Auth\LogoutAction;
use SlimRack\Application\Actions\Machine\MachineAction;
use SlimRack\Application\Actions\Provider\ProviderAction;
use SlimRack\Application\Actions\Currency\CurrencyAction;
use SlimRack\Application\Actions\Settings\SettingsAction;
use SlimRack\Application\Actions\Api\MachineApiAction;
use SlimRack\Application\Actions\Api\ProviderApiAction;
use SlimRack\Application\Actions\Api\StatsApiAction;
use SlimRack\Application\Actions\Api\CountryApiAction;
use SlimRack\Application\Actions\Api\PaymentCycleApiAction;
use SlimRack\Application\Middleware\AuthMiddleware;
use SlimRack\Application\Middleware\CsrfMiddleware;
use SlimRack\Application\Middleware\ApiKeyMiddleware;

return function (App $app): void {
    // =========================================================================
    // Public Routes
    // =========================================================================

    // Login page
    $app->get('/login', LoginAction::class)->setName('login');
    $app->post('/login', LoginSubmitAction::class)->setName('login.submit');

    // =========================================================================
    // Protected Web Routes (require authentication)
    // =========================================================================

    $app->group('', function (RouteCollectorProxy $group) {
        // Dashboard / Home
        $group->get('/', HomeAction::class)->setName('home');
        $group->get('/dashboard', HomeAction::class)->setName('dashboard');

        // Logout
        $group->get('/logout', LogoutAction::class)->setName('logout');

        // =====================================================================
        // AJAX Routes (JSON responses)
        // =====================================================================

        $group->group('/ajax', function (RouteCollectorProxy $ajax) {
            // Machine routes
            $ajax->get('/machines', [MachineAction::class, 'list']);
            $ajax->post('/machines', [MachineAction::class, 'create']);
            $ajax->get('/machines/{id:\d+}', [MachineAction::class, 'get']);
            $ajax->put('/machines/{id:\d+}', [MachineAction::class, 'update']);
            $ajax->delete('/machines/{id:\d+}', [MachineAction::class, 'delete']);
            $ajax->post('/machines/{id:\d+}/renew', [MachineAction::class, 'renew']);
            $ajax->post('/machines/{id:\d+}/toggle-hidden', [MachineAction::class, 'toggleHidden']);
            $ajax->delete('/machines/batch', [MachineAction::class, 'batchDelete']);
            $ajax->get('/machines/cities', [MachineAction::class, 'cities']);

            // Provider routes
            $ajax->get('/providers', [ProviderAction::class, 'list']);
            $ajax->post('/providers', [ProviderAction::class, 'create']);
            $ajax->get('/providers/{id:\d+}', [ProviderAction::class, 'get']);
            $ajax->put('/providers/{id:\d+}', [ProviderAction::class, 'update']);
            $ajax->delete('/providers/{id:\d+}', [ProviderAction::class, 'delete']);

            // Currency routes
            $ajax->get('/currencies', [CurrencyAction::class, 'list']);
            $ajax->post('/currencies', [CurrencyAction::class, 'create']);
            $ajax->put('/currencies/{code:[A-Z]{3}}', [CurrencyAction::class, 'update']);
            $ajax->delete('/currencies/{code:[A-Z]{3}}', [CurrencyAction::class, 'delete']);

            // Settings routes
            $ajax->get('/settings', [SettingsAction::class, 'get']);
            $ajax->post('/settings', [SettingsAction::class, 'update']);

            // Data lists (for dropdowns)
            $ajax->get('/countries', [CountryApiAction::class, 'list']);
            $ajax->get('/payment-cycles', [PaymentCycleApiAction::class, 'list']);
        })->add(CsrfMiddleware::class);

    })->add(AuthMiddleware::class);

    // =========================================================================
    // REST API Routes (require API key)
    // =========================================================================

    $app->group('/api', function (RouteCollectorProxy $api) {
        // API version
        $api->get('', function (Request $request, Response $response): Response {
            $payload = json_encode([
                'success' => true,
                'data' => [
                    'name' => 'SlimRack API',
                    'version' => '1.0',
                ],
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Machines
        $api->get('/machines', [MachineApiAction::class, 'list']);
        $api->get('/machines/{id:\d+}', [MachineApiAction::class, 'get']);
        $api->post('/machines', [MachineApiAction::class, 'create']);
        $api->put('/machines/{id:\d+}', [MachineApiAction::class, 'update']);
        $api->delete('/machines/{id:\d+}', [MachineApiAction::class, 'delete']);

        // Providers
        $api->get('/providers', [ProviderApiAction::class, 'list']);
        $api->get('/providers/{id:\d+}', [ProviderApiAction::class, 'get']);

        // Statistics
        $api->get('/stats', [StatsApiAction::class, 'get']);

        // Reference data
        $api->get('/countries', [CountryApiAction::class, 'list']);
        $api->get('/payment-cycles', [PaymentCycleApiAction::class, 'list']);

        // CORS preflight
        $api->options('/{routes:.+}', function (Request $request, Response $response): Response {
            return $response;
        });
    })->add(ApiKeyMiddleware::class);
};
