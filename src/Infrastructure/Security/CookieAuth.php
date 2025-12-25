<?php

declare(strict_types=1);

namespace SlimRack\Infrastructure\Security;

/**
 * Cookie Auth
 *
 * Handles "Remember Me" functionality with encrypted cookies
 */
class CookieAuth
{
    private string $encryptionKey;
    private array $settings;
    private string $cookieName = 'slimrack_remember';
    private string $cipher = 'aes-256-ctr';

    public function __construct(string $encryptionKey, array $settings = [])
    {
        $this->encryptionKey = $encryptionKey;
        $this->settings = array_merge([
            'lifetime' => 30, // days
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ], $settings);
    }

    /**
     * Create a remember token and set the cookie
     */
    public function createRememberToken(string $username, string $passwordHash): void
    {
        $token = $this->generateToken($username, $passwordHash);
        $encrypted = $this->encrypt($token);

        $this->setCookie($encrypted);
    }

    /**
     * Validate the remember cookie and return username if valid
     */
    public function validateRememberToken(string $passwordHash): ?string
    {
        $encrypted = $_COOKIE[$this->cookieName] ?? null;

        if (!$encrypted) {
            return null;
        }

        $token = $this->decrypt($encrypted);

        if (!$token) {
            $this->clearRememberToken();
            return null;
        }

        // Token format: username|hash
        $parts = explode('|', $token, 2);

        if (count($parts) !== 2) {
            $this->clearRememberToken();
            return null;
        }

        [$username, $storedHash] = $parts;

        // Validate the hash
        $expectedHash = $this->createHash($username, $passwordHash);

        if (!hash_equals($expectedHash, $storedHash)) {
            $this->clearRememberToken();
            return null;
        }

        return $username;
    }

    /**
     * Clear the remember token cookie
     */
    public function clearRememberToken(): void
    {
        if (isset($_COOKIE[$this->cookieName])) {
            setcookie(
                $this->cookieName,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => $this->settings['secure'],
                    'httponly' => $this->settings['httponly'],
                    'samesite' => $this->settings['samesite'],
                ]
            );
            unset($_COOKIE[$this->cookieName]);
        }
    }

    /**
     * Check if remember cookie exists
     */
    public function hasRememberToken(): bool
    {
        return isset($_COOKIE[$this->cookieName]) && !empty($_COOKIE[$this->cookieName]);
    }

    /**
     * Generate token string
     */
    private function generateToken(string $username, string $passwordHash): string
    {
        $hash = $this->createHash($username, $passwordHash);
        return $username . '|' . $hash;
    }

    /**
     * Create hash for token validation
     */
    private function createHash(string $username, string $passwordHash): string
    {
        return hash_hmac('sha256', $username . $passwordHash, $this->encryptionKey);
    }

    /**
     * Encrypt data using AES-256-CTR
     */
    private function encrypt(string $data): string
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            return '';
        }

        // Prepend IV to encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     */
    private function decrypt(string $data): ?string
    {
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);

        if (strlen($decoded) < $ivLength) {
            return null;
        }

        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Set the remember cookie
     */
    private function setCookie(string $value): void
    {
        $expires = time() + ($this->settings['lifetime'] * 24 * 60 * 60);

        setcookie(
            $this->cookieName,
            $value,
            [
                'expires' => $expires,
                'path' => '/',
                'secure' => $this->settings['secure'],
                'httponly' => $this->settings['httponly'],
                'samesite' => $this->settings['samesite'],
            ]
        );
    }
}
