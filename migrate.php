#!/usr/bin/php
<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

define('LOG_FILE', __DIR__ . '/migration.log'); // Logdatei

echo "######################### WARNING ################################\n";
echo "Use this script at your own risk.\n"; 
echo "The author assumes no liability for any damages caused by its usage.\n";
echo "This script will migrate data from PostgreSQL to MariaDB.\n";
echo "PLEASE READ THE README.MD FILE BEFORE USING THIS SCRIPT.\n";
echo "##################################################################\n";
echo "Press [Enter] to continue or Ctrl+C to exit.\n";
fgets(STDIN);

const PGSQL_TO_MARIADB_TYPES = [
    'smallint'                    => 'INT',
    'integer'                     => 'INT',
    'bigint'                      => 'BIGINT',
    'boolean'                     => 'TINYINT(1)',
    'character varying'           => 'VARCHAR(255)',
    'text'                        => 'TEXT',
    'timestamp without time zone' => 'DATETIME',
    'date'                        => 'DATE',
    'numeric'                     => 'DECIMAL(20,6)',
];

const BATCH_SIZE = 100; // Batch-Größe für Inserts
const DEFAULT_ENGINE = 'InnoDB';
const CHARSET = 'utf8mb4';

// Farben für die Konsolenausgabe
const COLORS = [
    'green'  => "\033[32m",
    'red'    => "\033[31m",
    'yellow' => "\033[33m",
    'reset'  => "\033[0m",
];

/**
 * Log a message to the log file.
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . " - [$level] $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Load environment variables from a .env file.
 */
function loadEnv(string $filePath): void
{
    if (!file_exists($filePath)) {
        logMessage('Missing .env file.', 'ERROR');
        exit('ERROR: Missing .env file.');
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv("$key=$value");
    }
}

/**
 * Output a message with color.
 */
function colorizeOutput(string $message, string $color): void
{
    echo COLORS[$color] . $message . COLORS['reset'] . "\n";
}

/**
 * Create a PDO connection.
 */
function createPDOConnection(string $dsn, string $user, string $password): PDO
{
    try {
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4', // For MariaDB
            PDO::ATTR_PERSISTENT         => true,
        ]);
    } catch (PDOException $e) {
        logMessage('Database connection failed: ' . $e->getMessage(), 'ERROR');
        colorizeOutput('ERROR: Unable to connect to one of the databases.', 'red');
        exit("ERROR: Unable to connect to one of the databases.\n");
    }
}

/**
 * Main execution function.
 */
function main(): void
{
    loadEnv(__DIR__ . '/.env');

    $pgsqlConfig = [
        'dsn' => sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('PGSQL_HOST') ?: 'localhost',
            getenv('PGSQL_PORT') ?: '5432',
            getenv('PGSQL_DBNAME') ?: 'your_dbname'
        ),
        'user'     => getenv('PGSQL_USER') ?: 'your_user',
        'password' => getenv('PGSQL_PASSWORD') ?: 'your_password',
    ];

    $mariadbConfig = [
        'dsn' => sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            getenv('MARIADB_HOST') ?: 'localhost',
            getenv('MARIADB_PORT') ?: '3306',
            getenv('MARIADB_DBNAME') ?: 'your_dbname'
        ),
        'user'     => getenv('MARIADB_USER') ?: 'your_user',
        'password' => getenv('MARIADB_PASSWORD') ?: 'your_password',
    ];

    $pgsql = createPDOConnection($pgsqlConfig['dsn'], $pgsqlConfig['user'], $pgsqlConfig['password']);
    $mariadb = createPDOConnection($mariadbConfig['dsn'], $mariadbConfig['user'], $mariadbConfig['password']);

    colorizeOutput("### Starting Database Migration ###", 'yellow');
    migrateDatabase($pgsql, $mariadb, getenv('TABLE_ENGINE') ?: DEFAULT_ENGINE);
    colorizeOutput("\nMigration completed successfully!", 'green');
}

/**
 * Migrate tables and data from PostgreSQL to MariaDB.
 */
function migrateDatabase(PDO $pgsql, PDO $mariadb, string $tableEngine): void
{
    $tables = $pgsql->query(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!$tables) {
        logMessage('No tables found in the PostgreSQL database.', 'WARNING');
        colorizeOutput('No tables found in the PostgreSQL database.', 'yellow');
        return;
    }

    $totalTables = count($tables);
    foreach ($tables as $index => $tableName) {
        echo sprintf("[%d/%d] Migrating table: %s\n", $index + 1, $totalTables, $tableName);

        $pgsqlColumns = fetchTableColumns($pgsql, $tableName);
        if (!$pgsqlColumns) {
            logMessage("No columns found for table `$tableName`. Skipping.", 'WARNING');
            echo "  No columns found for table `$tableName`. Skipping.\n";
            continue;
        }

        createMariaDBTable($mariadb, $tableName, $pgsqlColumns, $tableEngine);
        transferTableData($pgsql, $mariadb, $tableName, $pgsqlColumns);
    }
}

/**
 * Fetch columns from a PostgreSQL table.
 */
function fetchTableColumns(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->prepare(
        'SELECT column_name, data_type, is_nullable, column_default, ordinal_position, 
        CASE 
            WHEN column_default LIKE \'nextval(%::regclass)\' THEN 1 
            ELSE 0 
        END as is_auto_increment
         FROM information_schema.columns 
         WHERE table_name = :table_name'
    );
    $stmt->execute(['table_name' => $tableName]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create a MariaDB table based on PostgreSQL schema.
 */
function createMariaDBTable(PDO $mariadb, string $tableName, array $columns, string $tableEngine): void
{
    $columnsSql = [];
    $primaryKey = null;

    foreach ($columns as $col) {
        $type = PGSQL_TO_MARIADB_TYPES[$col['data_type']] ?? 'TEXT';
        $nullable = $col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
        $autoIncrement = ($col['is_auto_increment'] == 1) ? 'AUTO_INCREMENT' : '';

        if ($autoIncrement && !$primaryKey) {
            $primaryKey = $col['column_name'];
            $columnsSql[] = sprintf('`%s` %s %s %s PRIMARY KEY', $col['column_name'], $type, $nullable, $autoIncrement);
        } else {
            $columnsSql[] = sprintf('`%s` %s %s %s', $col['column_name'], $type, $nullable, $autoIncrement);
        }
    }

    if (!$primaryKey) {
        $primaryKey = $columns[0]['column_name'];
    }

    $sql = sprintf(
        'CREATE TABLE IF NOT EXISTS `%s` (%s) ENGINE=%s DEFAULT CHARSET=%s',
        $tableName,
        implode(', ', $columnsSql),
        $tableEngine,
        CHARSET
    );

    try {
        $mariadb->exec($sql);
        logMessage("Created table `$tableName`.", 'INFO');
    } catch (PDOException $e) {
        logMessage("Error creating table `$tableName`: " . $e->getMessage(), 'ERROR');
        echo "Error creating table `$tableName`: " . $e->getMessage() . "\n";
    }
}

/**
 * Sanitize row data before inserting it into MariaDB.
 */
function sanitizeRow(array &$row, array $columns): void
{
    foreach ($columns as $col) {
        $colName = $col['column_name'];
        $dataType = $col['data_type'];
        $isNullable = $col['is_nullable'] === 'YES';

        // Handle empty strings and NULL values
        if (!isset($row[$colName]) || $row[$colName] === '') {
            if (!$isNullable) {
                // Default values for NOT NULL columns
                if (in_array($dataType, ['integer', 'bigint', 'smallint', 'numeric'], true)) {
                    $row[$colName] = 0; // Default for numeric types
                } elseif ($dataType === 'boolean') {
                    $row[$colName] = 0; // Default for boolean types
                } else {
                    $row[$colName] = ''; // Default for text types
                }
            } else {
                $row[$colName] = null; // Allow NULL for nullable columns
            }
        }

        // Boolean sanitization: map values to 0/1
        if ($dataType === 'boolean') {
            $row[$colName] = filter_var($row[$colName], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
    }
}

/**
 * Transfer data from PostgreSQL to MariaDB using batch inserts for performance.
 */
function transferTableData(PDO $pgsql, PDO $mariadb, string $tableName, array $columns): void
{
    $columnNames = array_column($columns, 'column_name');
    $columnPlaceholders = implode(',', array_fill(0, count($columnNames), '?'));
    $columnsSql = implode('`,`', $columnNames); // MariaDB format with backticks

    // Für PostgreSQL benötigen wir doppelte Anführungszeichen für Spaltennamen
    $pgsqlColumnsSql = implode('","', $columnNames); // PostgreSQL format with double quotes

    $selectStmt = $pgsql->prepare("SELECT \"$pgsqlColumnsSql\" FROM \"$tableName\"");

    $selectStmt->execute();

    $batch = [];
    while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
        sanitizeRow($row, $columns);
        $batch[] = array_values($row);

        if (count($batch) >= BATCH_SIZE) {
            $insertStmt = $mariadb->prepare(
                "INSERT INTO `$tableName` (`$columnsSql`) VALUES ($columnPlaceholders)"
            );
            foreach ($batch as $data) {
                $insertStmt->execute($data);
            }
            $batch = []; // Reset batch
        }
    }

    // Insert remaining rows if any
    if ($batch) {
        $insertStmt = $mariadb->prepare(
            "INSERT INTO `$tableName` (`$columnsSql`) VALUES ($columnPlaceholders)"
        );
        foreach ($batch as $data) {
            $insertStmt->execute($data);
        }
    }

    logMessage("Data transferred for table `$tableName`.", 'INFO');
}

main();
