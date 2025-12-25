<?php

declare(strict_types=1);

namespace SlimRack\Domain\PaymentCycle;

use SlimRack\Infrastructure\Database\Connection;

/**
 * PaymentCycle Repository
 *
 * Handles all database operations for payment cycles
 */
class PaymentCycleRepository
{
    private Connection $db;
    private string $table = 'payment_cycle';

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Find all payment cycles
     */
    public function findAll(): array
    {
        return $this->db->select($this->table, [], '', [], 'month ASC');
    }

    /**
     * Find a payment cycle by ID
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne($this->table, [], 'payment_cycle_id = ?', [$id]);
    }

    /**
     * Find a payment cycle by name
     */
    public function findByName(string $name): ?array
    {
        return $this->db->selectOne($this->table, [], 'name = ?', [$name]);
    }

    /**
     * Check if payment cycle exists
     */
    public function exists(int $id): bool
    {
        return $this->db->exists($this->table, 'payment_cycle_id = ?', [$id]);
    }

    /**
     * Count payment cycles
     */
    public function count(): int
    {
        return $this->db->count($this->table);
    }

    /**
     * Get payment cycles with machine count
     */
    public function findAllWithMachineCount(): array
    {
        $sql = "
            SELECT
                pc.*,
                COUNT(m.machine_id) as machine_count
            FROM {$this->table} pc
            LEFT JOIN machine m ON pc.payment_cycle_id = m.payment_cycle_id
            GROUP BY pc.payment_cycle_id
            ORDER BY pc.month ASC
        ";

        return $this->db->fetchAll($sql);
    }

    /**
     * Calculate next due date based on payment cycle
     */
    public function calculateNextDueDate(int $paymentCycleId, string $currentDueDate): ?string
    {
        $cycle = $this->findById($paymentCycleId);

        if (!$cycle) {
            return null;
        }

        $date = new \DateTime($currentDueDate);
        $date->modify("+{$cycle['month']} months");

        return $date->format('Y-m-d');
    }
}
