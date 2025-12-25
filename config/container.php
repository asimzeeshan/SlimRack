<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use SlimRack\Infrastructure\Database\Connection;
use SlimRack\Infrastructure\Security\CsrfGuard;
use SlimRack\Infrastructure\Security\CookieAuth;
use SlimRack\Infrastructure\Session\SessionManager;
use SlimRack\Domain\Machine\MachineRepository;
use SlimRack\Domain\Provider\ProviderRepository;
use SlimRack\Domain\Currency\CurrencyRepository;
use SlimRack\Domain\Country\CountryRepository;
use SlimRack\Domain\PaymentCycle\PaymentCycleRepository;

return [
    // Settings
    'settings' => function (): array {
        return require CONFIG_PATH . '/settings.php';
    },

    // Logger
    LoggerInterface::class => function (ContainerInterface $c): LoggerInterface {
        $settings = $c->get('settings')['logger'];

        $logger = new Logger($settings['name']);

        // Ensure log directory exists
        $logDir = dirname($settings['path']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger->pushHandler(new StreamHandler($settings['path'], $settings['level']));

        return $logger;
    },

    // Twig View
    Twig::class => function (ContainerInterface $c): Twig {
        $settings = $c->get('settings')['twig'];

        // Ensure cache directory exists
        if ($settings['cache'] && !is_dir($settings['cache'])) {
            mkdir($settings['cache'], 0755, true);
        }

        $twig = Twig::create($settings['path'], [
            'cache' => $settings['debug'] ? false : $settings['cache'],
            'debug' => $settings['debug'],
            'auto_reload' => $settings['auto_reload'],
        ]);

        // Add global variables
        $appSettings = $c->get('settings')['app'];
        $twig->getEnvironment()->addGlobal('app', [
            'name' => $appSettings['name'],
            'version' => $appSettings['version'],
            'debug' => $appSettings['debug'],
        ]);

        return $twig;
    },

    // Database Connection
    Connection::class => function (ContainerInterface $c): Connection {
        $settings = $c->get('settings')['database'];

        // For SQLite, convert relative path to absolute
        if ($settings['driver'] === 'sqlite' && !str_starts_with($settings['database'], '/')) {
            $settings['database'] = ROOT_PATH . '/' . $settings['database'];

            // Ensure database directory exists
            $dbDir = dirname($settings['database']);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
        }

        return Connection::create($settings);
    },

    // Session Manager
    SessionManager::class => function (ContainerInterface $c): SessionManager {
        $settings = $c->get('settings')['session'];
        return new SessionManager($settings);
    },

    // CSRF Guard
    CsrfGuard::class => function (ContainerInterface $c): CsrfGuard {
        $session = $c->get(SessionManager::class);
        $settings = $c->get('settings')['csrf'];
        return new CsrfGuard($session, $settings);
    },

    // Cookie Auth
    CookieAuth::class => function (ContainerInterface $c): CookieAuth {
        $settings = $c->get('settings');
        return new CookieAuth(
            $settings['app']['key'],
            $settings['cookie']
        );
    },

    // Repositories
    MachineRepository::class => function (ContainerInterface $c): MachineRepository {
        return new MachineRepository($c->get(Connection::class));
    },

    ProviderRepository::class => function (ContainerInterface $c): ProviderRepository {
        return new ProviderRepository($c->get(Connection::class));
    },

    CurrencyRepository::class => function (ContainerInterface $c): CurrencyRepository {
        return new CurrencyRepository($c->get(Connection::class));
    },

    CountryRepository::class => function (ContainerInterface $c): CountryRepository {
        return new CountryRepository($c->get(Connection::class));
    },

    PaymentCycleRepository::class => function (ContainerInterface $c): PaymentCycleRepository {
        return new PaymentCycleRepository($c->get(Connection::class));
    },
];
