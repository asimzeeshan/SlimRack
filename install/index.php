<?php

declare(strict_types=1);

/**
 * SlimRack Installation Wizard
 *
 * This script handles the initial setup of SlimRack:
 * - Checks system requirements
 * - Configures database connection
 * - Runs migrations and seeds
 * - Sets up admin credentials
 * - Generates .env file
 */

// Prevent access if already installed
if (file_exists(__DIR__ . '/../.env')) {
    header('Location: /');
    exit;
}

session_start();

define('ROOT_PATH', dirname(__DIR__));

$step = $_GET['step'] ?? 'requirements';
$error = null;
$success = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($step) {
            case 'database':
                $result = handleDatabaseStep();
                if ($result === true) {
                    header('Location: ?step=admin');
                    exit;
                }
                $error = $result;
                break;

            case 'admin':
                $result = handleAdminStep();
                if ($result === true) {
                    header('Location: ?step=complete');
                    exit;
                }
                $error = $result;
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * Check system requirements
 */
function checkRequirements(): array
{
    $requirements = [];

    // PHP Version
    $requirements['php_version'] = [
        'name' => 'PHP Version',
        'required' => '8.1+',
        'current' => PHP_VERSION,
        'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
    ];

    // PDO Extension
    $requirements['pdo'] = [
        'name' => 'PDO Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('pdo') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('pdo'),
    ];

    // PDO SQLite
    $requirements['pdo_sqlite'] = [
        'name' => 'PDO SQLite Driver',
        'required' => 'Recommended',
        'current' => extension_loaded('pdo_sqlite') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('pdo_sqlite'),
        'optional' => true,
    ];

    // PDO MySQL
    $requirements['pdo_mysql'] = [
        'name' => 'PDO MySQL Driver',
        'required' => 'Recommended',
        'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('pdo_mysql'),
        'optional' => true,
    ];

    // OpenSSL
    $requirements['openssl'] = [
        'name' => 'OpenSSL Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('openssl') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('openssl'),
    ];

    // mbstring
    $requirements['mbstring'] = [
        'name' => 'mbstring Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
        'passed' => extension_loaded('mbstring'),
    ];

    // Storage directory writable
    $storageWritable = is_writable(ROOT_PATH . '/storage');
    $requirements['storage_writable'] = [
        'name' => 'Storage Directory Writable',
        'required' => 'Writable',
        'current' => $storageWritable ? 'Writable' : 'Not Writable',
        'passed' => $storageWritable,
    ];

    // Root directory writable (for .env)
    $rootWritable = is_writable(ROOT_PATH);
    $requirements['root_writable'] = [
        'name' => 'Root Directory Writable',
        'required' => 'Writable (for .env)',
        'current' => $rootWritable ? 'Writable' : 'Not Writable',
        'passed' => $rootWritable,
    ];

    return $requirements;
}

/**
 * Handle database configuration step
 */
function handleDatabaseStep(): bool|string
{
    $driver = $_POST['db_driver'] ?? 'sqlite';

    if ($driver === 'sqlite') {
        $dbPath = ROOT_PATH . '/storage/database/slimrack.sqlite';
        $dbDir = dirname($dbPath);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            return 'Failed to create SQLite database: ' . $e->getMessage();
        }

        $_SESSION['db_config'] = [
            'driver' => 'sqlite',
            'database' => 'storage/database/slimrack.sqlite',
        ];

    } else {
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? '3306';
        $database = $_POST['db_database'] ?? '';
        $username = $_POST['db_username'] ?? '';
        $password = $_POST['db_password'] ?? '';

        if (empty($database)) {
            return 'Database name is required';
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            return 'Failed to connect to MySQL: ' . $e->getMessage();
        }

        $_SESSION['db_config'] = [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    // Run migrations
    try {
        runMigrations($pdo, $driver);
        runSeeds($pdo);
    } catch (Exception $e) {
        return 'Failed to initialize database: ' . $e->getMessage();
    }

    return true;
}

/**
 * Run database migrations
 */
function runMigrations(PDO $pdo, string $driver): void
{
    $migrationFile = ROOT_PATH . '/database/migrations/001_initial_schema.php';
    $migration = require $migrationFile;

    // Create a minimal Connection-like object for the migration
    $db = new class($pdo, $driver) {
        private PDO $pdo;
        private string $driver;

        public function __construct(PDO $pdo, string $driver)
        {
            $this->pdo = $pdo;
            $this->driver = $driver;
        }

        public function isSqlite(): bool
        {
            return $this->driver === 'sqlite';
        }

        public function statement(string $sql): bool
        {
            return $this->pdo->prepare($sql)->execute();
        }
    };

    $migration->up($db);
}

/**
 * Run database seeds
 */
function runSeeds(PDO $pdo): void
{
    $seedFile = ROOT_PATH . '/database/seeds/initial_data.php';
    $seeder = require $seedFile;

    // Create a minimal Connection-like object for the seeder
    $db = new class($pdo) {
        private PDO $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function exists(string $table, string $where, array $bindings): bool
        {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
            $stmt->execute($bindings);
            return (int) $stmt->fetchColumn() > 0;
        }

        public function insert(string $table, array $data): int
        {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
            return (int) $this->pdo->lastInsertId();
        }
    };

    $seeder->run($db);
}

/**
 * Handle admin setup step
 */
function handleAdminStep(): bool|string
{
    $username = trim($_POST['admin_username'] ?? '');
    $password = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['admin_password_confirm'] ?? '';

    // Validate username
    if (!preg_match('/^[a-zA-Z0-9]{4,20}$/', $username)) {
        return 'Username must be 4-20 alphanumeric characters';
    }

    // Validate password
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one digit';
    }

    if ($password !== $confirmPassword) {
        return 'Passwords do not match';
    }

    // Generate .env file
    $dbConfig = $_SESSION['db_config'] ?? [];
    $appKey = bin2hex(random_bytes(16));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $envContent = "# SlimRack Configuration\n";
    $envContent .= "# Generated on " . date('Y-m-d H:i:s') . "\n\n";

    $envContent .= "# Database Configuration\n";
    $envContent .= "DB_DRIVER={$dbConfig['driver']}\n";

    if ($dbConfig['driver'] === 'mysql') {
        $envContent .= "DB_HOST={$dbConfig['host']}\n";
        $envContent .= "DB_PORT={$dbConfig['port']}\n";
        $envContent .= "DB_DATABASE={$dbConfig['database']}\n";
        $envContent .= "DB_USERNAME={$dbConfig['username']}\n";
        $envContent .= "DB_PASSWORD={$dbConfig['password']}\n";
    } else {
        $envContent .= "DB_DATABASE={$dbConfig['database']}\n";
    }

    $envContent .= "\n# Application Settings\n";
    $envContent .= "APP_KEY={$appKey}\n";
    $envContent .= "APP_DEBUG=false\n";
    $envContent .= "APP_URL=http://localhost\n";

    $envContent .= "\n# Authentication\n";
    $envContent .= "AUTH_USERNAME={$username}\n";
    $envContent .= "AUTH_PASSWORD_HASH={$passwordHash}\n";

    $envContent .= "\n# API Keys (comma-separated)\n";
    $envContent .= "API_KEYS=\n";

    $envContent .= "\n# Session Settings\n";
    $envContent .= "SESSION_LIFETIME=120\n";
    $envContent .= "SESSION_NAME=slimrack_session\n";

    $envContent .= "\n# Cookie Settings\n";
    $envContent .= "COOKIE_LIFETIME=30\n";
    $envContent .= "COOKIE_SECURE=false\n";
    $envContent .= "COOKIE_HTTPONLY=true\n";

    if (file_put_contents(ROOT_PATH . '/.env', $envContent) === false) {
        return 'Failed to write .env file. Please check directory permissions.';
    }

    // Clear session
    unset($_SESSION['db_config']);

    return true;
}

/**
 * Check if all required tests pass
 */
function allRequirementsPassed(array $requirements): bool
{
    foreach ($requirements as $req) {
        if (!($req['optional'] ?? false) && !$req['passed']) {
            return false;
        }
    }
    // Need at least one database driver
    return $requirements['pdo_sqlite']['passed'] || $requirements['pdo_mysql']['passed'];
}

$requirements = checkRequirements();
$canProceed = allRequirementsPassed($requirements);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - SlimRack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; min-height: 100vh; }
        .install-container { max-width: 700px; margin: 2rem auto; }
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .step { flex: 1; text-align: center; padding: 1rem; position: relative; }
        .step::after { content: ''; position: absolute; top: 50%; right: 0; width: 50%; height: 2px; background: #dee2e6; }
        .step:last-child::after { display: none; }
        .step::before { content: ''; position: absolute; top: 50%; left: 0; width: 50%; height: 2px; background: #dee2e6; }
        .step:first-child::before { display: none; }
        .step.active .step-number, .step.completed .step-number { background: #2c3e50; color: white; }
        .step-number { width: 40px; height: 40px; border-radius: 50%; background: #dee2e6; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; position: relative; z-index: 1; }
        .step.completed .step-number { background: #18bc9c; }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="text-center mb-4">
            <h1><i class="bi bi-server me-2"></i>SlimRack</h1>
            <p class="text-muted">Installation Wizard</p>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?php echo $step === 'requirements' ? 'active' : ($step !== 'requirements' ? 'completed' : ''); ?>">
                <span class="step-number">1</span>
                <div class="small mt-2">Requirements</div>
            </div>
            <div class="step <?php echo $step === 'database' ? 'active' : (in_array($step, ['admin', 'complete']) ? 'completed' : ''); ?>">
                <span class="step-number">2</span>
                <div class="small mt-2">Database</div>
            </div>
            <div class="step <?php echo $step === 'admin' ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>">
                <span class="step-number">3</span>
                <div class="small mt-2">Admin</div>
            </div>
            <div class="step <?php echo $step === 'complete' ? 'active' : ''; ?>">
                <span class="step-number">4</span>
                <div class="small mt-2">Complete</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if ($step === 'requirements'): ?>
                <!-- Requirements Step -->
                <h4 class="card-title mb-4">System Requirements</h4>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>Required</th>
                            <th>Current</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requirements as $req): ?>
                        <tr>
                            <td><?php echo $req['name']; ?></td>
                            <td><?php echo $req['required']; ?></td>
                            <td><?php echo $req['current']; ?></td>
                            <td>
                                <?php if ($req['passed']): ?>
                                <span class="badge bg-success"><i class="bi bi-check"></i> Pass</span>
                                <?php elseif ($req['optional'] ?? false): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-dash"></i> Optional</span>
                                <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x"></i> Fail</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="d-flex justify-content-end">
                    <?php if ($canProceed): ?>
                    <a href="?step=database" class="btn btn-primary">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    <?php else: ?>
                    <button class="btn btn-secondary" disabled>Fix requirements first</button>
                    <?php endif; ?>
                </div>

                <?php elseif ($step === 'database'): ?>
                <!-- Database Step -->
                <h4 class="card-title mb-4">Database Configuration</h4>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Database Driver</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="db_driver" id="dbSqlite" value="sqlite" checked>
                            <label class="btn btn-outline-primary" for="dbSqlite">
                                <i class="bi bi-file-earmark-binary me-1"></i>SQLite
                            </label>

                            <input type="radio" class="btn-check" name="db_driver" id="dbMysql" value="mysql"
                                   <?php echo !extension_loaded('pdo_mysql') ? 'disabled' : ''; ?>>
                            <label class="btn btn-outline-primary" for="dbMysql">
                                <i class="bi bi-database me-1"></i>MySQL
                            </label>
                        </div>
                        <div class="form-text">SQLite is recommended for simple setups. MySQL is better for larger deployments.</div>
                    </div>

                    <div id="mysqlConfig" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="dbHost" class="form-label">Host</label>
                                <input type="text" class="form-control" id="dbHost" name="db_host" value="localhost">
                            </div>
                            <div class="col-md-4">
                                <label for="dbPort" class="form-label">Port</label>
                                <input type="number" class="form-control" id="dbPort" name="db_port" value="3306">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="dbDatabase" class="form-label">Database Name</label>
                            <input type="text" class="form-control" id="dbDatabase" name="db_database">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dbUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="dbUsername" name="db_username">
                            </div>
                            <div class="col-md-6">
                                <label for="dbPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="dbPassword" name="db_password">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="?step=requirements" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Next <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

                <script>
                    document.querySelectorAll('input[name="db_driver"]').forEach(function(radio) {
                        radio.addEventListener('change', function() {
                            document.getElementById('mysqlConfig').style.display =
                                this.value === 'mysql' ? 'block' : 'none';
                        });
                    });
                </script>

                <?php elseif ($step === 'admin'): ?>
                <!-- Admin Step -->
                <h4 class="card-title mb-4">Admin Account</h4>

                <form method="POST">
                    <div class="mb-3">
                        <label for="adminUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="adminUsername" name="admin_username"
                               pattern="[a-zA-Z0-9]{4,20}" required>
                        <div class="form-text">4-20 alphanumeric characters</div>
                    </div>
                    <div class="mb-3">
                        <label for="adminPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="adminPassword" name="admin_password" required>
                        <div class="form-text">Minimum 8 characters with uppercase, lowercase, and digit</div>
                    </div>
                    <div class="mb-3">
                        <label for="adminPasswordConfirm" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="adminPasswordConfirm" name="admin_password_confirm" required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="?step=database" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Install <i class="bi bi-check-lg ms-1"></i>
                        </button>
                    </div>
                </form>

                <?php elseif ($step === 'complete'): ?>
                <!-- Complete Step -->
                <div class="text-center py-4">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">Installation Complete!</h4>
                    <p class="text-muted">SlimRack has been successfully installed.</p>

                    <div class="alert alert-info text-start mt-4">
                        <h6><i class="bi bi-info-circle me-2"></i>Next Steps</h6>
                        <ul class="mb-0">
                            <li>Delete or rename the <code>/install</code> directory for security</li>
                            <li>Set <code>APP_DEBUG=false</code> in production</li>
                            <li>Configure your web server to point to <code>/public</code></li>
                        </ul>
                    </div>

                    <a href="/" class="btn btn-primary btn-lg mt-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>

                <?php endif; ?>
            </div>
        </div>

        <p class="text-center text-muted mt-3">
            <small>SlimRack v1.0.0</small>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
