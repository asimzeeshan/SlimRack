<?php

declare(strict_types=1);

namespace SlimRack\Infrastructure\Security;

use SlimRack\Infrastructure\Session\SessionManager;

/**
 * CSRF Guard
 *
 * Provides CSRF token generation and validation
 */
class CsrfGuard
{
    private SessionManager $session;
    private array $settings;
    private string $tokenKey = '_csrf_token';
    private string $tokenTimeKey = '_csrf_token_time';

    public function __construct(SessionManager $session, array $settings = [])
    {
        $this->session = $session;
        $this->settings = array_merge([
            'tokenLifetime' => 3600, // 1 hour
            'tokenName' => '_csrf_token',
        ], $settings);
    }

    /**
     * Generate a new CSRF token
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->session->set($this->tokenKey, $token);
        $this->session->set($this->tokenTimeKey, time());

        return $token;
    }

    /**
     * Get the current CSRF token (or generate if none exists)
     */
    public function getToken(): string
    {
        $token = $this->session->get($this->tokenKey);
        $tokenTime = $this->session->get($this->tokenTimeKey, 0);

        // Generate new token if none exists or if expired
        if (!$token || $this->isExpired($tokenTime)) {
            return $this->generateToken();
        }

        return $token;
    }

    /**
     * Validate a CSRF token
     */
    public function validateToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $storedToken = $this->session->get($this->tokenKey);
        $tokenTime = $this->session->get($this->tokenTimeKey, 0);

        // Check if token matches and is not expired
        if (!$storedToken || !hash_equals($storedToken, $token)) {
            return false;
        }

        if ($this->isExpired($tokenTime)) {
            return false;
        }

        return true;
    }

    /**
     * Check if token is expired
     */
    private function isExpired(int $tokenTime): bool
    {
        return (time() - $tokenTime) > $this->settings['tokenLifetime'];
    }

    /**
     * Get the token input name
     */
    public function getTokenName(): string
    {
        return $this->settings['tokenName'];
    }

    /**
     * Generate HTML hidden input with CSRF token
     */
    public function getTokenField(): string
    {
        $name = htmlspecialchars($this->getTokenName(), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');

        return sprintf('<input type="hidden" name="%s" value="%s">', $name, $value);
    }

    /**
     * Get token for use in AJAX requests (as header or body)
     */
    public function getTokenData(): array
    {
        return [
            'name' => $this->getTokenName(),
            'value' => $this->getToken(),
        ];
    }

    /**
     * Regenerate token after successful validation (for single-use tokens)
     */
    public function regenerateToken(): string
    {
        return $this->generateToken();
    }

    /**
     * Clear the current token
     */
    public function clearToken(): void
    {
        $this->session->remove($this->tokenKey);
        $this->session->remove($this->tokenTimeKey);
    }
}
