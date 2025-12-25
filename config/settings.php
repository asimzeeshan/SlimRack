<?php

declare(strict_types=1);

/**
 * Application settings
 * Loads configuration from environment variables
 */

return [
    'app' => [
        'name' => 'SlimRack',
        'version' => '1.0.0',
        'key' => $_ENV['APP_KEY'] ?? 'CHANGE_THIS_TO_A_RANDOM_32_CHARACTER_STRING',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
        'basePath' => $_ENV['APP_BASE_PATH'] ?? '',
    ],

    'database' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'sqlite',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'storage/database/slimrack.sqlite',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'auth' => [
        'username' => $_ENV['AUTH_USERNAME'] ?? 'admin',
        'passwordHash' => $_ENV['AUTH_PASSWORD_HASH'] ?? '',
    ],

    'api' => [
        'keys' => array_filter(explode(',', $_ENV['API_KEYS'] ?? '')),
    ],

    'session' => [
        'name' => $_ENV['SESSION_NAME'] ?? 'slimrack_session',
        'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
        'secure' => filter_var($_ENV['COOKIE_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => filter_var($_ENV['COOKIE_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'samesite' => 'Lax',
    ],

    'cookie' => [
        'lifetime' => (int) ($_ENV['COOKIE_LIFETIME'] ?? 30),
        'secure' => filter_var($_ENV['COOKIE_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => filter_var($_ENV['COOKIE_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'samesite' => 'Lax',
    ],

    'twig' => [
        'path' => TEMPLATES_PATH,
        'cache' => STORAGE_PATH . '/cache/twig',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'auto_reload' => true,
    ],

    'logger' => [
        'name' => 'slimrack',
        'path' => STORAGE_PATH . '/logs/app.log',
        'level' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ? \Monolog\Level::Debug
            : \Monolog\Level::Info,
    ],

    'csrf' => [
        'tokenLifetime' => 3600, // 1 hour
        'tokenName' => '_csrf_token',
    ],
];
