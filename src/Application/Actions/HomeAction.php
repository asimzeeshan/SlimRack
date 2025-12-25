<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use SlimRack\Domain\Machine\MachineRepository;
use SlimRack\Domain\Provider\ProviderRepository;
use SlimRack\Domain\Country\CountryRepository;
use SlimRack\Domain\Currency\CurrencyRepository;
use SlimRack\Domain\PaymentCycle\PaymentCycleRepository;
use SlimRack\Infrastructure\Security\CsrfGuard;

/**
 * Home Action
 *
 * Displays the main dashboard
 */
class HomeAction
{
    public function __construct(
        private Twig $twig,
        private MachineRepository $machineRepo,
        private ProviderRepository $providerRepo,
        private CountryRepository $countryRepo,
        private CurrencyRepository $currencyRepo,
        private PaymentCycleRepository $paymentCycleRepo,
        private CsrfGuard $csrf
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        // Get data for the dashboard
        $machines = $this->machineRepo->findAllWithDetails();
        $providers = $this->providerRepo->findAllWithMachineCount();
        $countries = $this->countryRepo->findAll();
        $currencies = $this->currencyRepo->findAllWithUsdRates();
        $paymentCycles = $this->paymentCycleRepo->findAll();
        $statistics = $this->machineRepo->getStatistics();

        // Get CSRF token for forms
        $csrfToken = $this->csrf->getToken();
        $csrfName = $this->csrf->getTokenName();

        return $this->twig->render($response, 'home.twig', [
            'machines' => $machines,
            'providers' => $providers,
            'countries' => $countries,
            'currencies' => $currencies,
            'paymentCycles' => $paymentCycles,
            'statistics' => $statistics,
            'csrf' => [
                'name' => $csrfName,
                'value' => $csrfToken,
            ],
            'virtualizationTypes' => [
                'KVM', 'XEN', 'HyperV', 'VMWare', 'OpenStack', 'OpenVZ', 'LXD', 'Dedicated'
            ],
            'diskTypes' => [
                'SSD', 'NVMe', 'HDD', 'RAID'
            ],
        ]);
    }
}
