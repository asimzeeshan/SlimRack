<?php

declare(strict_types=1);

namespace SlimRack\Infrastructure\Database;

/**
 * Database Migrator
 *
 * Simple migration system for creating and updating database schema
 */
class Migrator
{
    private Connection $db;
    private string $migrationsPath;
    private string $migrationsTable = 'migrations';

    public function __construct(Connection $db, string $migrationsPath)
    {
        $this->db = $db;
        $this->migrationsPath = rtrim($migrationsPath, '/');
    }

    /**
     * Run all pending migrations
     *
     * @return array List of executed migrations
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $executed = $this->getExecutedMigrations();
        $pending = $this->getPendingMigrations($executed);
        $results = [];

        foreach ($pending as $migration) {
            $this->runMigration($migration);
            $results[] = $migration;
        }

        return $results;
    }

    /**
     * Rollback last batch of migrations
     *
     * @return array List of rolled back migrations
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();

        $lastBatch = $this->getLastBatchNumber();
        if ($lastBatch === 0) {
            return [];
        }

        $migrations = $this->db->fetchAll(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY migration DESC",
            [$lastBatch]
        );

        $results = [];
        foreach ($migrations as $row) {
            $this->rollbackMigration($row['migration']);
            $results[] = $row['migration'];
        }

        return $results;
    }

    /**
     * Reset all migrations (rollback all)
     *
     * @return array List of rolled back migrations
     */
    public function reset(): array
    {
        $this->ensureMigrationsTable();

        $migrations = $this->db->fetchAll(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY migration DESC"
        );

        $results = [];
        foreach ($migrations as $row) {
            $this->rollbackMigration($row['migration']);
            $results[] = $row['migration'];
        }

        return $results;
    }

    /**
     * Get migration status
     *
     * @return array
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $executed = $this->getExecutedMigrations();
        $files = $this->getMigrationFiles();
        $status = [];

        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            $status[] = [
                'migration' => $name,
                'status' => in_array($name, $executed) ? 'Ran' : 'Pending',
            ];
        }

        return $status;
    }

    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTable(): void
    {
        if ($this->db->isSqlite()) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INTEGER PRIMARY KEY,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $this->db->statement($sql);
    }

    /**
     * Get list of executed migrations
     */
    private function getExecutedMigrations(): array
    {
        $result = $this->db->fetchAll(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY migration"
        );
        return array_column($result, 'migration');
    }

    /**
     * Get list of pending migrations
     */
    private function getPendingMigrations(array $executed): array
    {
        $files = $this->getMigrationFiles();
        $pending = [];

        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            if (!in_array($name, $executed)) {
                $pending[] = $name;
            }
        }

        sort($pending);
        return $pending;
    }

    /**
     * Get all migration files
     */
    private function getMigrationFiles(): array
    {
        $pattern = $this->migrationsPath . '/*.php';
        $files = glob($pattern);
        return $files ?: [];
    }

    /**
     * Get migration name from file path
     */
    private function getMigrationName(string $file): string
    {
        return pathinfo($file, PATHINFO_FILENAME);
    }

    /**
     * Get migration file path from name
     */
    private function getMigrationPath(string $name): string
    {
        return $this->migrationsPath . '/' . $name . '.php';
    }

    /**
     * Run a single migration
     */
    private function runMigration(string $name): void
    {
        $path = $this->getMigrationPath($name);

        if (!file_exists($path)) {
            throw new \RuntimeException("Migration file not found: {$path}");
        }

        $migration = require $path;

        if (!is_object($migration) || !method_exists($migration, 'up')) {
            throw new \RuntimeException("Invalid migration file: {$path}");
        }

        $this->db->beginTransaction();

        try {
            $migration->up($this->db);

            $batch = $this->getNextBatchNumber();
            $this->db->insert($this->migrationsTable, [
                'migration' => $name,
                'batch' => $batch,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Rollback a single migration
     */
    private function rollbackMigration(string $name): void
    {
        $path = $this->getMigrationPath($name);

        if (!file_exists($path)) {
            // Migration file doesn't exist, just remove from table
            $this->db->delete($this->migrationsTable, 'migration = ?', [$name]);
            return;
        }

        $migration = require $path;

        if (!is_object($migration)) {
            throw new \RuntimeException("Invalid migration file: {$path}");
        }

        $this->db->beginTransaction();

        try {
            if (method_exists($migration, 'down')) {
                $migration->down($this->db);
            }

            $this->db->delete($this->migrationsTable, 'migration = ?', [$name]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get the last batch number
     */
    private function getLastBatchNumber(): int
    {
        $result = $this->db->fetchColumn(
            "SELECT MAX(batch) FROM {$this->migrationsTable}"
        );
        return (int) $result;
    }

    /**
     * Get the next batch number
     */
    private function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }
}
