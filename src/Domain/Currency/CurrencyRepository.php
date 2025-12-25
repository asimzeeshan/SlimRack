<?php

declare(strict_types=1);

namespace SlimRack\Domain\Currency;

use SlimRack\Infrastructure\Database\Connection;

/**
 * Currency Repository
 *
 * Handles all database operations for currency rates
 */
class CurrencyRepository
{
    private Connection $db;
    private string $table = 'currency_rate';

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Find all currencies
     */
    public function findAll(): array
    {
        return $this->db->select($this->table, [], '', [], 'currency_code ASC');
    }

    /**
     * Find all currencies with USD-converted rates
     */
    public function findAllWithUsdRates(): array
    {
        $currencies = $this->findAll();

        // Convert rates to USD (rate is stored as multiplied by 10000)
        foreach ($currencies as &$currency) {
            $currency['rate_decimal'] = $currency['rate'] / 10000;
            $currency['usd_equivalent'] = 1 / $currency['rate_decimal'];
        }

        return $currencies;
    }

    /**
     * Find a currency by code
     */
    public function findByCode(string $code): ?array
    {
        return $this->db->selectOne($this->table, [], 'currency_code = ?', [strtoupper($code)]);
    }

    /**
     * Create or update a currency
     */
    public function upsert(string $code, int $rate): bool
    {
        $code = strtoupper($code);

        $existing = $this->findByCode($code);

        if ($existing) {
            return $this->db->update($this->table, ['rate' => $rate], 'currency_code = ?', [$code]) > 0;
        }

        return $this->db->insert($this->table, [
            'currency_code' => $code,
            'rate' => $rate,
        ]) > 0;
    }

    /**
     * Create a new currency
     */
    public function create(string $code, int $rate): bool
    {
        $code = strtoupper($code);

        if ($this->exists($code)) {
            return false;
        }

        return $this->db->insert($this->table, [
            'currency_code' => $code,
            'rate' => $rate,
        ]) > 0;
    }

    /**
     * Update a currency rate
     */
    public function update(string $code, int $rate): int
    {
        return $this->db->update($this->table, ['rate' => $rate], 'currency_code = ?', [strtoupper($code)]);
    }

    /**
     * Delete a currency
     */
    public function delete(string $code): int
    {
        // Don't allow deleting USD (base currency)
        if (strtoupper($code) === 'USD') {
            return 0;
        }

        return $this->db->delete($this->table, 'currency_code = ?', [strtoupper($code)]);
    }

    /**
     * Check if currency exists
     */
    public function exists(string $code): bool
    {
        return $this->db->exists($this->table, 'currency_code = ?', [strtoupper($code)]);
    }

    /**
     * Count currencies
     */
    public function count(): int
    {
        return $this->db->count($this->table);
    }

    /**
     * Convert amount from one currency to another
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if (strtoupper($fromCurrency) === strtoupper($toCurrency)) {
            return $amount;
        }

        $fromRate = $this->findByCode($fromCurrency);
        $toRate = $this->findByCode($toCurrency);

        if (!$fromRate || !$toRate) {
            return $amount;
        }

        // Convert to USD first, then to target currency
        $usdAmount = $amount * (10000 / $fromRate['rate']);
        $targetAmount = $usdAmount * ($toRate['rate'] / 10000);

        return round($targetAmount, 2);
    }

    /**
     * Convert amount to USD
     */
    public function convertToUsd(float $amount, string $currency): float
    {
        return $this->convert($amount, $currency, 'USD');
    }
}
