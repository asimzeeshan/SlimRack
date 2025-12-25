<?php

declare(strict_types=1);

namespace SlimRack\Application\Actions\Settings;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SlimRack\Infrastructure\Session\SessionManager;

/**
 * Settings Action
 *
 * Handles user settings (theme, pagination, etc.)
 */
class SettingsAction
{
    public function __construct(
        private SessionManager $session
    ) {}

    /**
     * Get current settings
     */
    public function get(Request $request, Response $response): Response
    {
        $settings = [
            'theme' => $this->session->get('settings_theme', 'light'),
            'page_length' => $this->session->get('settings_page_length', 25),
        ];

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Theme setting
        if (isset($data['theme'])) {
            $theme = in_array($data['theme'], ['light', 'dark']) ? $data['theme'] : 'light';
            $this->session->set('settings_theme', $theme);
        }

        // Page length setting
        if (isset($data['page_length'])) {
            $validLengths = [10, 25, 50, 100];
            $pageLength = in_array((int) $data['page_length'], $validLengths)
                ? (int) $data['page_length']
                : 25;
            $this->session->set('settings_page_length', $pageLength);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'message' => 'Settings updated successfully',
                'settings' => [
                    'theme' => $this->session->get('settings_theme', 'light'),
                    'page_length' => $this->session->get('settings_page_length', 25),
                ],
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
