<?php

declare(strict_types=1);

use SlimRack\Infrastructure\Database\Connection;

/**
 * Initial database schema migration
 *
 * Creates all required tables for SlimRack
 */
return new class {
    public function up(Connection $db): void
    {
        if ($db->isSqlite()) {
            $this->createSqliteTables($db);
        } else {
            $this->createMysqlTables($db);
        }
    }

    public function down(Connection $db): void
    {
        // Drop tables in reverse order (due to foreign keys)
        $tables = ['machine', 'provider', 'currency_rate', 'country', 'payment_cycle'];

        foreach ($tables as $table) {
            $db->statement("DROP TABLE IF EXISTS {$table}");
        }
    }

    private function createSqliteTables(Connection $db): void
    {
        // Payment Cycle table
        $db->statement("
            CREATE TABLE IF NOT EXISTS payment_cycle (
                payment_cycle_id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                month INTEGER NOT NULL
            )
        ");

        // Country table
        $db->statement("
            CREATE TABLE IF NOT EXISTS country (
                country_code TEXT PRIMARY KEY,
                country_name TEXT NOT NULL
            )
        ");

        // Currency Rate table
        $db->statement("
            CREATE TABLE IF NOT EXISTS currency_rate (
                currency_code TEXT PRIMARY KEY,
                rate INTEGER NOT NULL DEFAULT 10000
            )
        ");

        // Provider table
        $db->statement("
            CREATE TABLE IF NOT EXISTS provider (
                provider_id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                website TEXT,
                control_panel_name TEXT,
                control_panel_url TEXT,
                date_created TEXT NOT NULL,
                date_modified TEXT NOT NULL
            )
        ");

        // Machine table
        $db->statement("
            CREATE TABLE IF NOT EXISTS machine (
                machine_id INTEGER PRIMARY KEY,
                is_hidden INTEGER NOT NULL DEFAULT 0,
                is_nat INTEGER NOT NULL DEFAULT 0,
                label TEXT NOT NULL,
                virtualization TEXT,
                cpu_speed INTEGER DEFAULT 0,
                cpu_core INTEGER DEFAULT 0,
                memory INTEGER DEFAULT 0,
                swap INTEGER DEFAULT 0,
                disk_type TEXT,
                disk_space INTEGER DEFAULT 0,
                bandwidth INTEGER DEFAULT 0,
                ip_address TEXT,
                country_code TEXT,
                city_name TEXT,
                price INTEGER DEFAULT 0,
                currency_code TEXT DEFAULT 'USD',
                payment_cycle_id INTEGER,
                due_date TEXT,
                notes TEXT,
                date_created TEXT NOT NULL,
                date_modified TEXT NOT NULL,
                provider_id INTEGER,
                FOREIGN KEY (provider_id) REFERENCES provider(provider_id) ON DELETE SET NULL,
                FOREIGN KEY (payment_cycle_id) REFERENCES payment_cycle(payment_cycle_id),
                FOREIGN KEY (country_code) REFERENCES country(country_code)
            )
        ");

        // Create indexes for better query performance
        $db->statement("CREATE INDEX IF NOT EXISTS idx_machine_provider ON machine(provider_id)");
        $db->statement("CREATE INDEX IF NOT EXISTS idx_machine_country ON machine(country_code)");
        $db->statement("CREATE INDEX IF NOT EXISTS idx_machine_due_date ON machine(due_date)");
        $db->statement("CREATE INDEX IF NOT EXISTS idx_machine_hidden ON machine(is_hidden)");
    }

    private function createMysqlTables(Connection $db): void
    {
        // Payment Cycle table
        $db->statement("
            CREATE TABLE IF NOT EXISTS payment_cycle (
                payment_cycle_id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                month INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Country table
        $db->statement("
            CREATE TABLE IF NOT EXISTS country (
                country_code CHAR(2) PRIMARY KEY,
                country_name VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Currency Rate table
        $db->statement("
            CREATE TABLE IF NOT EXISTS currency_rate (
                currency_code CHAR(3) PRIMARY KEY,
                rate INT NOT NULL DEFAULT 10000
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Provider table
        $db->statement("
            CREATE TABLE IF NOT EXISTS provider (
                provider_id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                website VARCHAR(255),
                control_panel_name VARCHAR(100),
                control_panel_url VARCHAR(255),
                date_created DATETIME NOT NULL,
                date_modified DATETIME NOT NULL,
                INDEX idx_provider_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Machine table
        $db->statement("
            CREATE TABLE IF NOT EXISTS machine (
                machine_id INT AUTO_INCREMENT PRIMARY KEY,
                is_hidden TINYINT(1) NOT NULL DEFAULT 0,
                is_nat TINYINT(1) NOT NULL DEFAULT 0,
                label VARCHAR(255) NOT NULL,
                virtualization VARCHAR(50),
                cpu_speed INT DEFAULT 0,
                cpu_core INT DEFAULT 0,
                memory INT DEFAULT 0,
                swap INT DEFAULT 0,
                disk_type VARCHAR(20),
                disk_space INT DEFAULT 0,
                bandwidth INT DEFAULT 0,
                ip_address TEXT,
                country_code CHAR(2),
                city_name VARCHAR(100),
                price INT DEFAULT 0,
                currency_code CHAR(3) DEFAULT 'USD',
                payment_cycle_id INT,
                due_date DATE,
                notes TEXT,
                date_created DATETIME NOT NULL,
                date_modified DATETIME NOT NULL,
                provider_id INT,
                INDEX idx_machine_provider (provider_id),
                INDEX idx_machine_country (country_code),
                INDEX idx_machine_due_date (due_date),
                INDEX idx_machine_hidden (is_hidden),
                FOREIGN KEY (provider_id) REFERENCES provider(provider_id) ON DELETE SET NULL,
                FOREIGN KEY (payment_cycle_id) REFERENCES payment_cycle(payment_cycle_id),
                FOREIGN KEY (country_code) REFERENCES country(country_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
};
