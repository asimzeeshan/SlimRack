<?php

declare(strict_types=1);

namespace SlimRack\Domain\Provider;

use SlimRack\Infrastructure\Database\Connection;

/**
 * Provider Repository
 *
 * Handles all database operations for hosting providers
 */
class ProviderRepository
{
    private Connection $db;
    private string $table = 'provider';

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Find all providers
     */
    public function findAll(): array
    {
        return $this->db->select($this->table, [], '', [], 'name ASC');
    }

    /**
     * Find all providers with machine count
     */
    public function findAllWithMachineCount(): array
    {
        $sql = "
            SELECT
                p.*,
                COUNT(m.machine_id) as machine_count
            FROM provider p
            LEFT JOIN machine m ON p.provider_id = m.provider_id
            GROUP BY p.provider_id
            ORDER BY p.name ASC
        ";

        return $this->db->fetchAll($sql);
    }

    /**
     * Find a provider by ID
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne($this->table, [], 'provider_id = ?', [$id]);
    }

    /**
     * Find a provider by ID with machine count
     */
    public function findByIdWithMachineCount(int $id): ?array
    {
        $sql = "
            SELECT
                p.*,
                COUNT(m.machine_id) as machine_count
            FROM provider p
            LEFT JOIN machine m ON p.provider_id = m.provider_id
            WHERE p.provider_id = ?
            GROUP BY p.provider_id
        ";

        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Create a new provider
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $data['date_created'] = $now;
        $data['date_modified'] = $now;

        return $this->db->insert($this->table, $data);
    }

    /**
     * Update a provider
     */
    public function update(int $id, array $data): int
    {
        $data['date_modified'] = date('Y-m-d H:i:s');

        return $this->db->update($this->table, $data, 'provider_id = ?', [$id]);
    }

    /**
     * Delete a provider
     *
     * Note: This will set provider_id to NULL on associated machines
     */
    public function delete(int $id): int
    {
        return $this->db->delete($this->table, 'provider_id = ?', [$id]);
    }

    /**
     * Check if provider exists by name
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $where = 'name = ?';
        $bindings = [$name];

        if ($excludeId !== null) {
            $where .= ' AND provider_id != ?';
            $bindings[] = $excludeId;
        }

        return $this->db->exists($this->table, $where, $bindings);
    }

    /**
     * Count providers
     */
    public function count(): int
    {
        return $this->db->count($this->table);
    }

    /**
     * Search providers
     */
    public function search(string $query): array
    {
        $sql = "
            SELECT
                p.*,
                COUNT(m.machine_id) as machine_count
            FROM provider p
            LEFT JOIN machine m ON p.provider_id = m.provider_id
            WHERE p.name LIKE ? OR p.website LIKE ?
            GROUP BY p.provider_id
            ORDER BY p.name ASC
        ";

        $searchTerm = '%' . $query . '%';

        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);
    }
}
