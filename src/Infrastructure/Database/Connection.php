<?php

declare(strict_types=1);

namespace SlimRack\Infrastructure\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database Connection
 *
 * Simple PDO wrapper supporting SQLite and MySQL/MariaDB
 */
class Connection
{
    private PDO $pdo;
    private string $driver;
    private ?string $lastError = null;

    private function __construct(PDO $pdo, string $driver)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
    }

    /**
     * Create a new database connection
     *
     * @param array $config Database configuration
     * @return self
     * @throws PDOException
     */
    public static function create(array $config): self
    {
        $driver = $config['driver'] ?? 'sqlite';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . $config['database'];
            $pdo = new PDO($dsn, null, null, $options);

            // Enable foreign keys for SQLite
            $stmt = $pdo->prepare('PRAGMA foreign_keys = ON');
            $stmt->execute();
        } else {
            // MySQL/MariaDB
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                $options
            );

            // Set MySQL specific options
            $stmt = $pdo->prepare("SET NAMES 'utf8mb4'");
            $stmt->execute();
            $stmt = $pdo->prepare("SET time_zone = '+00:00'");
            $stmt->execute();
        }

        return new self($pdo, $driver);
    }

    /**
     * Get the database driver name
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Check if using SQLite
     */
    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    /**
     * Check if using MySQL
     */
    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    /**
     * Get the underlying PDO instance
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Run a raw SQL statement (for DDL operations)
     *
     * @param string $sql SQL statement
     * @return bool
     */
    public function statement(string $sql): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Execute a prepared SQL query
     *
     * @param string $sql SQL query
     * @param array $bindings Parameter bindings
     * @return PDOStatement|false
     */
    public function query(string $sql, array $bindings = []): PDOStatement|false
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Execute query and fetch all results
     *
     * @param string $sql SQL query
     * @param array $bindings Parameter bindings
     * @return array
     */
    public function fetchAll(string $sql, array $bindings = []): array
    {
        $stmt = $this->query($sql, $bindings);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Execute query and fetch single row
     *
     * @param string $sql SQL query
     * @param array $bindings Parameter bindings
     * @return array|null
     */
    public function fetchOne(string $sql, array $bindings = []): ?array
    {
        $stmt = $this->query($sql, $bindings);
        if (!$stmt) {
            return null;
        }
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute query and fetch single column value
     *
     * @param string $sql SQL query
     * @param array $bindings Parameter bindings
     * @return mixed
     */
    public function fetchColumn(string $sql, array $bindings = []): mixed
    {
        $stmt = $this->query($sql, $bindings);
        return $stmt ? $stmt->fetchColumn() : null;
    }

    /**
     * Insert a record into a table
     *
     * @param string $table Table name
     * @param array $data Column => value pairs
     * @return int|false Last insert ID or false on failure
     */
    public function insert(string $table, array $data): int|false
    {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $stmt = $this->query($sql, array_values($data));
        return $stmt ? (int) $this->pdo->lastInsertId() : false;
    }

    /**
     * Update records in a table
     *
     * @param string $table Table name
     * @param array $data Column => value pairs
     * @param string $where WHERE clause
     * @param array $bindings WHERE clause bindings
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where = '', array $bindings = []): int
    {
        if (empty($data)) {
            return 0;
        }

        $setParts = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setParts[] = $this->quoteIdentifier($column) . ' = ?';
            $values[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s',
            $this->quoteIdentifier($table),
            implode(', ', $setParts)
        );

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
            $values = array_merge($values, $bindings);
        }

        $stmt = $this->query($sql, $values);
        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Delete records from a table
     *
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $bindings WHERE clause bindings
     * @return int Number of affected rows
     */
    public function delete(string $table, string $where = '', array $bindings = []): int
    {
        $sql = sprintf('DELETE FROM %s', $this->quoteIdentifier($table));

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }

        $stmt = $this->query($sql, $bindings);
        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Select records from a table
     *
     * @param string $table Table name
     * @param array $columns Columns to select (empty for all)
     * @param string $where WHERE clause
     * @param array $bindings WHERE clause bindings
     * @param string $orderBy ORDER BY clause
     * @param int|null $limit LIMIT
     * @param int|null $offset OFFSET
     * @return array
     */
    public function select(
        string $table,
        array $columns = [],
        string $where = '',
        array $bindings = [],
        string $orderBy = '',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $columnsList = empty($columns) ? '*' : implode(', ', array_map([$this, 'quoteIdentifier'], $columns));

        $sql = sprintf('SELECT %s FROM %s', $columnsList, $this->quoteIdentifier($table));

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }

        if (!empty($orderBy)) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
            if ($offset !== null) {
                $sql .= ' OFFSET ' . $offset;
            }
        }

        return $this->fetchAll($sql, $bindings);
    }

    /**
     * Select a single record from a table
     *
     * @param string $table Table name
     * @param array $columns Columns to select (empty for all)
     * @param string $where WHERE clause
     * @param array $bindings WHERE clause bindings
     * @return array|null
     */
    public function selectOne(
        string $table,
        array $columns = [],
        string $where = '',
        array $bindings = []
    ): ?array {
        $result = $this->select($table, $columns, $where, $bindings, '', 1);
        return $result[0] ?? null;
    }

    /**
     * Count records in a table
     *
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $bindings WHERE clause bindings
     * @return int
     */
    public function count(string $table, string $where = '', array $bindings = []): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s', $this->quoteIdentifier($table));

        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }

        return (int) $this->fetchColumn($sql, $bindings);
    }

    /**
     * Check if a record exists
     *
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $bindings WHERE clause bindings
     * @return bool
     */
    public function exists(string $table, string $where, array $bindings = []): bool
    {
        return $this->count($table, $where, $bindings) > 0;
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in a transaction
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Quote an identifier (table or column name)
     */
    public function quoteIdentifier(string $identifier): string
    {
        // Handle table.column format
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);
            return implode('.', array_map([$this, 'quoteIdentifier'], $parts));
        }

        // Don't quote * or already quoted identifiers
        if ($identifier === '*' || str_starts_with($identifier, '`') || str_starts_with($identifier, '"')) {
            return $identifier;
        }

        // Use backticks for MySQL, double quotes for SQLite
        $quote = $this->isMysql() ? '`' : '"';
        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    /**
     * Quote a value
     */
    public function quote(string $value): string
    {
        return $this->pdo->quote($value);
    }

    /**
     * Check if a table exists
     */
    public function tableExists(string $table): bool
    {
        if ($this->isSqlite()) {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
        } else {
            $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_NAME = ?";
        }

        return $this->fetchColumn($sql, [$table]) !== false;
    }

    /**
     * Get list of tables
     */
    public function getTables(): array
    {
        if ($this->isSqlite()) {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
            return array_column($this->fetchAll($sql), 'name');
        }

        $sql = "SHOW TABLES";
        $result = $this->fetchAll($sql);
        return array_map(fn($row) => array_values($row)[0], $result);
    }
}
