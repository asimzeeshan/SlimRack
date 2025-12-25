<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

// Set the default timezone
date_default_timezone_set('UTC');

// Define paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');

// Autoload
require ROOT_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv->load();
}

// Build DI Container
$containerBuilder = new ContainerBuilder();

// Add container definitions
$containerBuilder->addDefinitions(CONFIG_PATH . '/container.php');

// Build container
$container = $containerBuilder->build();

// Create App with container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Get base path from container settings
$settings = $container->get('settings');
$basePath = $settings['app']['basePath'] ?? '';
if (!empty($basePath)) {
    $app->setBasePath($basePath);
}

// Register middleware
$middleware = require CONFIG_PATH . '/middleware.php';
$middleware($app);

// Register routes
$routes = require CONFIG_PATH . '/routes.php';
$routes($app);

// Run app
$app->run();
