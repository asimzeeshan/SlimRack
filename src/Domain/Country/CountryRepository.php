<?php

declare(strict_types=1);

namespace SlimRack\Domain\Country;

use SlimRack\Infrastructure\Database\Connection;

/**
 * Country Repository
 *
 * Handles all database operations for countries
 */
class CountryRepository
{
    private Connection $db;
    private string $table = 'country';

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Find all countries
     */
    public function findAll(): array
    {
        return $this->db->select($this->table, [], '', [], 'country_name ASC');
    }

    /**
     * Find a country by code
     */
    public function findByCode(string $code): ?array
    {
        return $this->db->selectOne($this->table, [], 'country_code = ?', [strtoupper($code)]);
    }

    /**
     * Search countries by name
     */
    public function search(string $query): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE country_name LIKE ? OR country_code LIKE ?
            ORDER BY country_name ASC
            LIMIT 20
        ";

        $searchTerm = '%' . $query . '%';

        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);
    }

    /**
     * Check if country exists
     */
    public function exists(string $code): bool
    {
        return $this->db->exists($this->table, 'country_code = ?', [strtoupper($code)]);
    }

    /**
     * Count countries
     */
    public function count(): int
    {
        return $this->db->count($this->table);
    }

    /**
     * Get countries used in machines
     */
    public function getUsedCountries(): array
    {
        $sql = "
            SELECT DISTINCT c.*
            FROM {$this->table} c
            INNER JOIN machine m ON c.country_code = m.country_code
            ORDER BY c.country_name ASC
        ";

        return $this->db->fetchAll($sql);
    }

    /**
     * Get countries with machine count
     */
    public function getCountriesWithMachineCount(): array
    {
        $sql = "
            SELECT
                c.*,
                COUNT(m.machine_id) as machine_count
            FROM {$this->table} c
            LEFT JOIN machine m ON c.country_code = m.country_code
            GROUP BY c.country_code
            HAVING machine_count > 0
            ORDER BY machine_count DESC, c.country_name ASC
        ";

        return $this->db->fetchAll($sql);
    }
}
