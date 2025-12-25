<?php

declare(strict_types=1);

use Slim\App;
use Slim\Views\TwigMiddleware;
use SlimRack\Application\Middleware\SessionMiddleware;

return function (App $app): void {
    $container = $app->getContainer();
    $settings = $container->get('settings');

    // Parse JSON body
    $app->addBodyParsingMiddleware();

    // Add Twig Middleware
    $app->add(TwigMiddleware::createFromContainer($app));

    // Session Middleware (starts session for web requests)
    $app->add(SessionMiddleware::class);

    // Routing Middleware
    $app->addRoutingMiddleware();

    // Error Middleware (should be last)
    $errorMiddleware = $app->addErrorMiddleware(
        $settings['app']['debug'],
        true,
        true
    );

    // Custom error handler for nice error pages
    if (!$settings['app']['debug']) {
        $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        // Could add custom error renderer here
    }
};
