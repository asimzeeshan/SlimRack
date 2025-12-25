<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use SlimRack\Infrastructure\Session\SessionManager;
use SlimRack\Infrastructure\Security\CsrfGuard;

/**
 * Login Action
 *
 * Displays the login page
 */
class LoginAction
{
    public function __construct(
        private Twig $twig,
        private SessionManager $session,
        private CsrfGuard $csrf
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        // Redirect if already logged in
        if ($this->session->get('authenticated') === true) {
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/');
        }

        $error = $this->session->getFlash('error');

        return $this->twig->render($response, 'auth/login.twig', [
            'error' => $error,
            'csrf' => [
                'name' => $this->csrf->getTokenName(),
                'value' => $this->csrf->getToken(),
            ],
        ]);
    }
}
