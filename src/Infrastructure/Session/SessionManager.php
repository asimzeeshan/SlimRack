<?php

declare(strict_types=1);

namespace SlimRack\Infrastructure\Session;

/**
 * Session Manager
 *
 * Handles PHP session management with security features
 */
class SessionManager
{
    private array $settings;
    private bool $started = false;

    public function __construct(array $settings = [])
    {
        $this->settings = array_merge([
            'name' => 'slimrack_session',
            'lifetime' => 120,
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ], $settings);
    }

    /**
     * Start the session
     */
    public function start(): bool
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return true;
        }

        $this->configure();
        $this->started = session_start();

        if ($this->started) {
            $this->preventFixation();
            $this->validateSession();
        }

        return $this->started;
    }

    /**
     * Configure session settings
     */
    private function configure(): void
    {
        session_name($this->settings['name']);

        $cookieParams = [
            'lifetime' => $this->settings['lifetime'] * 60,
            'path' => '/',
            'domain' => '',
            'secure' => $this->settings['secure'],
            'httponly' => $this->settings['httponly'],
            'samesite' => $this->settings['samesite'],
        ];

        session_set_cookie_params($cookieParams);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
    }

    /**
     * Prevent session fixation attacks
     */
    private function preventFixation(): void
    {
        $regenerateTime = $this->get('_regenerate_time', 0);
        $now = time();

        if ($regenerateTime === 0 || ($now - $regenerateTime) > 1800) {
            session_regenerate_id(true);
            $this->set('_regenerate_time', $now);
        }
    }

    /**
     * Validate session integrity
     */
    private function validateSession(): void
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!$this->has('_fingerprint')) {
            $this->set('_fingerprint', $this->generateFingerprint($userAgent));
            return;
        }

        $storedFingerprint = $this->get('_fingerprint');
        $currentFingerprint = $this->generateFingerprint($userAgent);

        if ($storedFingerprint !== $currentFingerprint) {
            $this->destroy();
            $this->start();
        }
    }

    /**
     * Generate session fingerprint
     */
    private function generateFingerprint(string $userAgent): string
    {
        return hash('sha256', $userAgent);
    }

    /**
     * Get a session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Clear all session data (except system keys)
     */
    public function clear(): void
    {
        $systemKeys = ['_fingerprint', '_regenerate_time', '_csrf_token'];

        foreach ($_SESSION as $key => $value) {
            if (!in_array($key, $systemKeys)) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * Destroy the session completely
     */
    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->started = false;
    }

    /**
     * Regenerate session ID
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Get session ID
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Check if session is started
     */
    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Flash a message (available only for next request)
     */
    public function flash(string $key, mixed $value): void
    {
        $flash = $this->get('_flash', []);
        $flash[$key] = $value;
        $this->set('_flash', $flash);
    }

    /**
     * Get and remove a flash message
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flash = $this->get('_flash', []);

        if (!isset($flash[$key])) {
            return $default;
        }

        $value = $flash[$key];
        unset($flash[$key]);
        $this->set('_flash', $flash);

        return $value;
    }

    /**
     * Check if flash message exists
     */
    public function hasFlash(string $key): bool
    {
        $flash = $this->get('_flash', []);
        return isset($flash[$key]);
    }
}
