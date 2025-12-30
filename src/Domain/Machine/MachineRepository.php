<?php

declare(strict_types=1);

namespace SlimRack\Domain\Machine;

use SlimRack\Infrastructure\Database\Connection;

/**
 * Machine Repository
 *
 * Handles all database operations for machines (servers)
 */
class MachineRepository
{
    private Connection $db;
    private string $table = 'machine';

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Find all machines with optional filtering
     */
    public function findAll(bool $includeHidden = false, ?int $limit = null, ?int $offset = null): array
    {
        $where = $includeHidden ? '' : 'is_hidden = 0';

        return $this->db->select(
            $this->table,
            [],
            $where,
            [],
            'date_created DESC',
            $limit,
            $offset
        );
    }

    /**
     * Find all machines with provider and country info
     */
    public function findAllWithDetails(bool $includeHidden = false): array
    {
        $sql = "
            SELECT
                m.*,
                p.name as provider_name,
                p.website as provider_website,
                p.control_panel_name,
                p.control_panel_url,
                c.country_name,
                pc.name as payment_cycle_name,
                pc.month as payment_cycle_months,
                cr.rate as currency_rate
            FROM machine m
            LEFT JOIN provider p ON m.provider_id = p.provider_id
            LEFT JOIN country c ON m.country_code = c.country_code
            LEFT JOIN payment_cycle pc ON m.payment_cycle_id = pc.payment_cycle_id
            LEFT JOIN currency_rate cr ON m.currency_code = cr.currency_code
        ";

        if (!$includeHidden) {
            $sql .= " WHERE m.is_hidden = 0";
        }

        $sql .= " ORDER BY m.date_created DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Find a machine by ID
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne($this->table, [], 'machine_id = ?', [$id]);
    }

    /**
     * Find a machine by ID with full details
     */
    public function findByIdWithDetails(int $id): ?array
    {
        $sql = "
            SELECT
                m.*,
                p.name as provider_name,
                p.website as provider_website,
                p.control_panel_name,
                p.control_panel_url,
                c.country_name,
                pc.name as payment_cycle_name,
                pc.month as payment_cycle_months,
                cr.rate as currency_rate
            FROM machine m
            LEFT JOIN provider p ON m.provider_id = p.provider_id
            LEFT JOIN country c ON m.country_code = c.country_code
            LEFT JOIN payment_cycle pc ON m.payment_cycle_id = pc.payment_cycle_id
            LEFT JOIN currency_rate cr ON m.currency_code = cr.currency_code
            WHERE m.machine_id = ?
        ";

        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Create a new machine
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $data['date_created'] = $now;
        $data['date_modified'] = $now;

        // Set defaults
        $data['is_hidden'] = $data['is_hidden'] ?? 0;
        $data['is_nat'] = $data['is_nat'] ?? 0;

        return $this->db->insert($this->table, $data);
    }

    /**
     * Update a machine
     */
    public function update(int $id, array $data): int
    {
        $data['date_modified'] = date('Y-m-d H:i:s');

        return $this->db->update($this->table, $data, 'machine_id = ?', [$id]);
    }

    /**
     * Delete a machine
     */
    public function delete(int $id): int
    {
        return $this->db->delete($this->table, 'machine_id = ?', [$id]);
    }

    /**
     * Delete multiple machines
     */
    public function deleteMany(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->delete($this->table, "machine_id IN ({$placeholders})", $ids);
    }

    /**
     * Toggle hidden status
     */
    public function toggleHidden(int $id): bool
    {
        $machine = $this->findById($id);

        if (!$machine) {
            return false;
        }

        $newValue = $machine['is_hidden'] ? 0 : 1;

        return $this->update($id, ['is_hidden' => $newValue]) > 0;
    }

    /**
     * Renew due date
     */
    public function renewDueDate(int $id): ?string
    {
        $machine = $this->findByIdWithDetails($id);

        if (!$machine || !$machine['due_date'] || !$machine['payment_cycle_months']) {
            return null;
        }

        // Calculate new due date
        $currentDue = new \DateTime($machine['due_date']);
        $currentDue->modify("+{$machine['payment_cycle_months']} months");
        $newDueDate = $currentDue->format('Y-m-d');

        $this->update($id, ['due_date' => $newDueDate]);

        return $newDueDate;
    }

    /**
     * Get distinct cities for autocomplete
     */
    public function getDistinctCities(string $query = ''): array
    {
        $sql = "SELECT DISTINCT city_name FROM {$this->table} WHERE city_name IS NOT NULL AND city_name != ''";
        $bindings = [];

        if (!empty($query)) {
            $sql .= " AND city_name LIKE ?";
            $bindings[] = '%' . $query . '%';
        }

        $sql .= " ORDER BY city_name LIMIT 20";

        $results = $this->db->fetchAll($sql, $bindings);

        return array_column($results, 'city_name');
    }

    /**
     * Count all machines
     */
    public function count(bool $includeHidden = false): int
    {
        $where = $includeHidden ? '' : 'is_hidden = 0';
        return $this->db->count($this->table, $where);
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        // Total machines
        $totalMachines = $this->count(true);
        $visibleMachines = $this->count(false);

        // Monthly cost (converted to USD using rates)
        // Price is stored in cents, divide by 100 to get dollars
        // Then divide by payment cycle months to get monthly cost
        // Then convert to USD using currency rates (rate is stored as rate * 10000)
        $sql = "
            SELECT
                SUM(
                    CASE
                        WHEN pc.month > 0 THEN (m.price / 100.0 / pc.month) * (10000.0 / COALESCE(cr.rate, 10000))
                        ELSE 0
                    END
                ) as monthly_cost
            FROM machine m
            LEFT JOIN payment_cycle pc ON m.payment_cycle_id = pc.payment_cycle_id
            LEFT JOIN currency_rate cr ON m.currency_code = cr.currency_code
            WHERE m.is_hidden = 0
        ";

        $result = $this->db->fetchOne($sql);
        $monthlyCost = round((float) ($result['monthly_cost'] ?? 0), 2);

        // By provider
        $sql = "
            SELECT p.name, COUNT(m.machine_id) as count
            FROM machine m
            LEFT JOIN provider p ON m.provider_id = p.provider_id
            WHERE m.is_hidden = 0
            GROUP BY m.provider_id
            ORDER BY count DESC
        ";
        $byProvider = $this->db->fetchAll($sql);

        // By country
        $sql = "
            SELECT c.country_name, COUNT(m.machine_id) as count
            FROM machine m
            LEFT JOIN country c ON m.country_code = c.country_code
            WHERE m.is_hidden = 0 AND m.country_code IS NOT NULL
            GROUP BY m.country_code
            ORDER BY count DESC
        ";
        $byCountry = $this->db->fetchAll($sql);

        return [
            'total_machines' => $totalMachines,
            'visible_machines' => $visibleMachines,
            'monthly_cost' => $monthlyCost,
            'by_provider' => $byProvider,
            'by_country' => $byCountry,
        ];
    }

    /**
     * Search machines
     */
    public function search(string $query, bool $includeHidden = false): array
    {
        $sql = "
            SELECT
                m.*,
                p.name as provider_name,
                c.country_name
            FROM machine m
            LEFT JOIN provider p ON m.provider_id = p.provider_id
            LEFT JOIN country c ON m.country_code = c.country_code
            WHERE (
                m.label LIKE ? OR
                m.ip_address LIKE ? OR
                m.city_name LIKE ? OR
                m.notes LIKE ? OR
                p.name LIKE ? OR
                c.country_name LIKE ?
            )
        ";

        if (!$includeHidden) {
            $sql .= " AND m.is_hidden = 0";
        }

        $sql .= " ORDER BY m.date_created DESC";

        $searchTerm = '%' . $query . '%';
        $bindings = array_fill(0, 6, $searchTerm);

        return $this->db->fetchAll($sql, $bindings);
    }
}
