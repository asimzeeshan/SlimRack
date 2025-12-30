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
use SlimRack\Application\Middleware\AuthMiddleware;
use SlimRack\Application\Middleware\CsrfMiddleware;
use SlimRack\Application\Middleware\ApiKeyMiddleware;
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

return [
    // View alias for TwigMiddleware::createFromContainer() compatibility
    'view' => function (ContainerInterface $c): Twig {
        return $c->get(Twig::class);
    },

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

    // Middleware
    AuthMiddleware::class => function (ContainerInterface $c): AuthMiddleware {
        return new AuthMiddleware(
            $c->get(SessionManager::class),
            $c->get(CookieAuth::class),
            $c->get('settings')['auth']
        );
    },

    CsrfMiddleware::class => function (ContainerInterface $c): CsrfMiddleware {
        return new CsrfMiddleware($c->get(CsrfGuard::class));
    },

    ApiKeyMiddleware::class => function (ContainerInterface $c): ApiKeyMiddleware {
        return new ApiKeyMiddleware($c->get('settings')['api']);
    },

    // Actions
    HomeAction::class => function (ContainerInterface $c): HomeAction {
        return new HomeAction(
            $c->get(Twig::class),
            $c->get(MachineRepository::class),
            $c->get(ProviderRepository::class),
            $c->get(CountryRepository::class),
            $c->get(CurrencyRepository::class),
            $c->get(PaymentCycleRepository::class),
            $c->get(CsrfGuard::class)
        );
    },

    LoginAction::class => function (ContainerInterface $c): LoginAction {
        return new LoginAction(
            $c->get(Twig::class),
            $c->get(SessionManager::class),
            $c->get(CsrfGuard::class)
        );
    },

    LoginSubmitAction::class => function (ContainerInterface $c): LoginSubmitAction {
        return new LoginSubmitAction(
            $c->get(SessionManager::class),
            $c->get(CsrfGuard::class),
            $c->get(CookieAuth::class),
            $c->get('settings')['auth']
        );
    },

    LogoutAction::class => function (ContainerInterface $c): LogoutAction {
        return new LogoutAction(
            $c->get(SessionManager::class),
            $c->get(CookieAuth::class)
        );
    },

    MachineAction::class => function (ContainerInterface $c): MachineAction {
        return new MachineAction($c->get(MachineRepository::class));
    },

    ProviderAction::class => function (ContainerInterface $c): ProviderAction {
        return new ProviderAction($c->get(ProviderRepository::class));
    },

    CurrencyAction::class => function (ContainerInterface $c): CurrencyAction {
        return new CurrencyAction($c->get(CurrencyRepository::class));
    },

    SettingsAction::class => function (ContainerInterface $c): SettingsAction {
        return new SettingsAction($c->get(SessionManager::class));
    },

    // API Actions
    MachineApiAction::class => function (ContainerInterface $c): MachineApiAction {
        return new MachineApiAction($c->get(MachineRepository::class));
    },

    ProviderApiAction::class => function (ContainerInterface $c): ProviderApiAction {
        return new ProviderApiAction($c->get(ProviderRepository::class));
    },

    StatsApiAction::class => function (ContainerInterface $c): StatsApiAction {
        return new StatsApiAction($c->get(MachineRepository::class));
    },

    CountryApiAction::class => function (ContainerInterface $c): CountryApiAction {
        return new CountryApiAction($c->get(CountryRepository::class));
    },

    PaymentCycleApiAction::class => function (ContainerInterface $c): PaymentCycleApiAction {
        return new PaymentCycleApiAction($c->get(PaymentCycleRepository::class));
    },
];
