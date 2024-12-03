#!/usr/bin/php
<?php

/**
 * PostgreSQL to MariaDB Migration Tool
 *
 * A robust PHP-based tool for migrating databases from PostgreSQL to MariaDB
 * while maintaining data integrity, relationships, and structure.
 *
 * Features:
 * - Automated schema and data migration
 * - Foreign key and index preservation
 * - Progress tracking and detailed logging
 * - Batch processing for optimal performance
 *
 * @author      PixoVoid <contact@pixovoid.net>
 * @copyright   2024 PixoVoid
 * @license     MIT License
 * @link        https://pixovoid.net
 * @link        https://github.com/PixoVoid-net/pgsql-mariadb-migrate
 *
 * @requires    PHP >= 8.3
 * @requires    ext-pdo
 * @requires    ext-pdo_pgsql
 * @requires    ext-pdo_mysql
 */

declare(strict_types=1);

// Improved error handling and logging for better user feedback
ini_set('display_errors', '0'); // Disable display errors on screen
ini_set('log_errors', '1'); // Enable error logging
ini_set('error_log', __DIR__ . '/migration_error.log'); // Log errors to the migration log file

// Enhanced script execution time management
set_time_limit(0); // Limit execution time to unlimited to prevent server overload

define('LOG_FILE', __DIR__ . '/migration' . date('Y-m-d-H-i') . '.log');

// Group all database connection functions
function createPDOConnection(string $host, string $port, string $dbname, string $user, string $password, string $engine = 'pgsql'): PDO
{
    $dsn = "$engine:host=$host;port=$port;dbname=$dbname";
    try {
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        logMessage("Successfully connected to the database: $dbname", 'INFO');
        return $pdo;
    } catch (PDOException $e) {
        logMessage("Connection failed: " . $e->getMessage(), 'ERROR');
        throw new RuntimeException("Database connection error. Please check the configuration and try again.");
    }
}

// Group all migration-related functions
function migrateDatabase(PDO $pgsql, PDO $mariadb): void
{
    profileStart('migrateDatabase');
    try {
        ConsoleOutput::showStatus("Analyzing Database", "Getting table dependencies...", 0, 5);
        $orderedTables = getTableOrder($pgsql);
        $totalTables = count($orderedTables);

        // Step 1: Create all tables first (without foreign keys)
        ConsoleOutput::showStatus("Creating Tables", "Preparing to create tables...", 1, 5);
        foreach ($orderedTables as $index => $tableName) {
            ConsoleOutput::showStatus("Creating Tables", "Creating table: $tableName", 1, 5);
            $columns = fetchTableColumns($pgsql, $tableName);
            createMariaDBTable($mariadb, $tableName, $columns);
        }

        // Step 2: Migrate data in batches
        ConsoleOutput::showStatus("Migrating Data", "Preparing to migrate data...", 2, 5);
        foreach ($orderedTables as $index => $tableName) {
            ConsoleOutput::showStatus("Migrating Data", "Migrating data for: $tableName", 2, 5);
            $columns = fetchTableColumns($pgsql, $tableName);
            transferTableDataInBatches($pgsql, $mariadb, $tableName, $columns);
        }

        // Step 3: Add foreign keys after all data is migrated
        ConsoleOutput::showStatus("Adding Foreign Keys", "Preparing to add foreign keys...", 3, 5);
        foreach ($orderedTables as $index => $tableName) {
            ConsoleOutput::showStatus("Adding Foreign Keys", "Adding foreign keys for: $tableName", 3, 5);
            $foreignKeys = fetchForeignKeys($pgsql, $tableName);
            addForeignKeyConstraints($mariadb, $tableName, $foreignKeys);
        }

        // Step 4: Add cascade constraints
        ConsoleOutput::showStatus("Adding Cascade Constraints", "Preparing to add cascade constraints...", 4, 5);
        foreach ($orderedTables as $index => $tableName) {
            ConsoleOutput::showStatus("Adding Cascade Constraints", "Adding cascade constraints for: $tableName", 4, 5);
            $foreignKeys = fetchForeignKeys($pgsql, $tableName);
            addCascadeConstraints($mariadb, $pgsql, $tableName, $foreignKeys);
        }

        // Step 5: Add indexes
        ConsoleOutput::showStatus("Creating Indexes", "Preparing to create indexes...", 5, 5);
        foreach ($orderedTables as $index => $tableName) {
            ConsoleOutput::showStatus("Creating Indexes", "Creating indexes for: $tableName", 5, 5);
            createIndexes($pgsql, $mariadb, $tableName);
        }

        ConsoleOutput::showSuccess("Migration completed successfully!");
    } catch (Exception $e) {
        ConsoleOutput::showError($e->getMessage());
        logMessage("Error during migration: " . $e->getMessage(), 'ERROR');
        throw $e;
    }
    profileEnd('migrateDatabase');
}

function transferTableDataInBatches(PDO $pgsql, PDO $mariadb, string $tableName, array $columns, int $batchSize = 1000): void
{
    $batchSize = DatabaseConfig::BATCH_SIZE;
    $offset = 0;
    do {
        $query = "SELECT * FROM $tableName LIMIT $batchSize OFFSET $offset";
        $stmt = $pgsql->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $mariadb->beginTransaction();
            foreach ($rows as $row) {
                sanitizeRow($row, $columns);
                insertRowIntoMariaDB($mariadb, $tableName, $row);
            }
            $mariadb->commit();
        }

        $offset += $batchSize;
    } while (count($rows) > 0);
    $stmt = null; // Close the statement
}

function insertRowIntoMariaDB(PDO $mariadb, string $tableName, array $row): void
{
    $columns = implode(", ", array_keys($row));
    $placeholders = implode(", ", array_fill(0, count($row), '?'));
    $query = "INSERT INTO $tableName ($columns) VALUES ($placeholders)";
    $stmt = $mariadb->prepare($query);
    $stmt->execute(array_values($row));
    $stmt = null; // Close the statement
}

function logMessage(string $message, string $level = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $formattedMessage, FILE_APPEND);
}

function loadEnv(string $filePath): void
{
    match (true) {
        !file_exists($filePath) => throw new InvalidArgumentException('Missing .env file.'),
        filesize($filePath) === 0 => throw new InvalidArgumentException('The .env file is empty.'),
        default => null
    };

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        ?: throw new RuntimeException('Failed to read the .env file.');

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            throw new InvalidArgumentException(
                "The line '$line' in the .env file is not in the correct format."
            );
        }

        [$key, $value] = explode('=', $line, 2);
        putenv("$key=$value");
    }
}

function fetchTableColumns(PDO $pdo, string $tableName): array
{
    static $cache = [];
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }
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
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cache[$tableName] = $result;
    return $result;
}

function createMariaDBTable(PDO $mariadb, string $tableName, array $columns, string $tableEngine = DatabaseConfig::DEFAULT_ENGINE): void
{
    $columnsSql = [];
    $primaryKey = null;

    foreach ($columns as $col) {
        $dataType = match ($col['data_type']) {
            'smallint' => DataType::SMALLINT,
            'integer' => DataType::INTEGER,
            'bigint' => DataType::BIGINT,
            'boolean' => DataType::BOOLEAN,
            'character varying' => DataType::CHARACTER_VARYING,
            'text' => DataType::TEXT,
            'timestamp without time zone' => DataType::TIMESTAMP,
            'date' => DataType::DATE,
            'numeric' => DataType::NUMERIC,
            default => DataType::TEXT,
        };

        $type = $dataType->toMariaDBType();
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
        'CREATE TABLE IF NOT EXISTS `%s` (%s) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s',
        $tableName,
        implode(', ', $columnsSql),
        $tableEngine,
        DatabaseConfig::CHARSET,
        DatabaseConfig::COLLATION
    );

    try {
        $mariadb->exec($sql);
        logMessage("Created table `$tableName`.", 'INFO');
    } catch (PDOException $e) {
        logMessage("Error creating table `$tableName`: " . $e->getMessage(), 'ERROR');
        echo "Error creating table `$tableName`: " . $e->getMessage() . "\n";
    }
}

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

// Utility function for validating and setting default actions
function validateAction(string $action, string $constraintName, string $tableName, string $type): string
{
    $validActions = ['NO ACTION', 'CASCADE', 'SET NULL', 'RESTRICT', 'SET DEFAULT'];
    $action = strtoupper($action);
    if (!in_array($action, $validActions)) {
        logMessage("Invalid $type action '$action' for $constraintName in $tableName. Defaulting to 'NO ACTION'.", 'WARNING');
        return 'NO ACTION';
    }
    return $action;
}

function createForeignKeyConstraints(PDO $pgsql, PDO $mariadb, string $tableName): void
{
    $query = <<<SQL
        SELECT
            tc.constraint_name,
            kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name,
            rc.update_rule,
            rc.delete_rule
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage ccu
            ON ccu.constraint_name = tc.constraint_name
        JOIN information_schema.referential_constraints rc
            ON rc.constraint_name = tc.constraint_name
        WHERE tc.constraint_type = 'FOREIGN KEY'
        AND tc.table_name = :tableName
    SQL;

    $stmt = $pgsql->prepare($query);
    $stmt->execute(['tableName' => $tableName]);
    $foreignKeys = $stmt->fetchAll();

    foreach ($foreignKeys as $fk) {
        $constraint = <<<SQL
            ALTER TABLE `$tableName` 
            ADD CONSTRAINT `{$fk['constraint_name']}` 
            FOREIGN KEY (`{$fk['column_name']}`) 
            REFERENCES `{$fk['foreign_table_name']}` (`{$fk['foreign_column_name']}`)
            ON DELETE {$fk['delete_rule']} 
            ON UPDATE {$fk['update_rule']}
        SQL;

        try {
            $mariadb->exec($constraint);
            logMessage("Added foreign key constraint {$fk['constraint_name']} to $tableName", 'INFO');
        } catch (PDOException $e) {
            logMessage("Error creating table `$tableName`: " . $e->getMessage(), 'ERROR');
        }
    }
}

function createIndexes(PDO $pgsql, PDO $mariadb, string $tableName): void
{
    $query = <<<SQL
        SELECT
            i.relname AS index_name,
            a.attname AS column_name,
            ix.indisunique AS is_unique
        FROM pg_class t
        JOIN pg_index ix ON t.oid = ix.indrelid
        JOIN pg_class i ON i.oid = ix.indexrelid
        JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
        WHERE t.relname = :tableName
        AND i.relname NOT LIKE '%_pkey'
    SQL;

    $stmt = $pgsql->prepare($query);
    $stmt->execute(['tableName' => $tableName]);
    $indexes = $stmt->fetchAll();

    foreach ($indexes as $idx) {
        $unique = $idx['is_unique'] ? 'UNIQUE' : '';
        $indexSql = <<<SQL
            CREATE $unique INDEX `{$idx['index_name']}` 
            ON `$tableName` (`{$idx['column_name']}`)
        SQL;

        try {
            $mariadb->exec($indexSql);
            logMessage("Created index {$idx['index_name']} on $tableName", 'INFO');
        } catch (PDOException $e) {
            logMessage("Failed to create index: " . $e->getMessage(), 'ERROR');
        }
    }
}

function addCascadeConstraints(PDO $mariadb, PDO $pgsql, string $tableName, array $foreignKeys): void
{
    // Check if the table exists in PostgreSQL
    $tableExistsSql = "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :tableName";
    $stmt = $pgsql->prepare($tableExistsSql);
    $stmt->execute(['tableName' => $tableName]);

    if ($stmt->rowCount() === 0) {
        logMessage("Table '$tableName' does not exist in PostgreSQL.", 'ERROR');
        return;
    }

    foreach ($foreignKeys as $foreignKey) {
        // Ensure unique constraint name by appending table name and column
        $constraintName = "fk_{$tableName}_{$foreignKey['column']}";

        // Default values for ON UPDATE and ON DELETE
        $onUpdate = '';
        $onDelete = '';

        // Check if ON UPDATE CASCADE is defined in PostgreSQL
        $pgsqlUpdateCheckSql = "
            SELECT 1 
            FROM information_schema.referential_constraints rc 
            JOIN information_schema.key_column_usage kcu 
              ON rc.constraint_name = kcu.constraint_name 
             AND rc.constraint_schema = kcu.constraint_schema 
            WHERE rc.constraint_schema = 'public' 
              AND rc.update_rule = 'CASCADE' 
              AND kcu.table_name = :tableName 
              AND kcu.column_name = :columnName
        ";
        $stmt = $pgsql->prepare($pgsqlUpdateCheckSql);
        $stmt->execute(['tableName' => $tableName, 'columnName' => $foreignKey['column']]);

        if ($stmt->rowCount() > 0) {
            $onUpdate = 'ON UPDATE CASCADE';
        }

        // Check if ON DELETE CASCADE is defined in PostgreSQL
        $pgsqlDeleteCheckSql = "
            SELECT 1 
            FROM information_schema.referential_constraints rc 
            JOIN information_schema.key_column_usage kcu 
              ON rc.constraint_name = kcu.constraint_name 
             AND rc.constraint_schema = kcu.constraint_schema 
            WHERE rc.constraint_schema = 'public' 
              AND rc.delete_rule = 'CASCADE' 
              AND kcu.table_name = :tableName 
              AND kcu.column_name = :columnName
        ";
        $stmt = $pgsql->prepare($pgsqlDeleteCheckSql);
        $stmt->execute(['tableName' => $tableName, 'columnName' => $foreignKey['column']]);

        if ($stmt->rowCount() > 0) {
            $onDelete = 'ON DELETE CASCADE';
        }

        // Construct the ALTER TABLE SQL statement dynamically
        $constraintSql = "
            ALTER TABLE `$tableName`
            ADD CONSTRAINT `$constraintName`
            FOREIGN KEY (`{$foreignKey['column']}`)
            REFERENCES `{$foreignKey['referenced_table']}`(`{$foreignKey['referenced_column']}`)
            $onUpdate
            $onDelete;
        ";

        try {
            // Verify if the referenced table and column exist in MariaDB
            $checkSql = "
                SELECT 1 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = :referencedTable 
                  AND COLUMN_NAME = :referencedColumn
            ";
            $stmt = $mariadb->prepare($checkSql);
            $stmt->execute([
                'referencedTable' => $foreignKey['referenced_table'],
                'referencedColumn' => $foreignKey['referenced_column']
            ]);

            if ($stmt->rowCount() > 0) {
                // Ensure the referenced column has an index
                $indexCheckSql = "SHOW INDEX FROM `{$foreignKey['referenced_table']}` WHERE Column_name = :referenced_column";
                $indexStmt = $mariadb->prepare($indexCheckSql);
                $indexStmt->execute([
                    ':referenced_column' => $foreignKey['referenced_column']
                ]);

                if ($indexStmt->rowCount() === 0) {
                    $createIndexSql = "CREATE INDEX idx_{$foreignKey['referenced_column']} ON `{$foreignKey['referenced_table']}`(`{$foreignKey['referenced_column']}`)";
                    $mariadb->exec($createIndexSql);
                }

                // Add the foreign key constraint
                $mariadb->exec($constraintSql);
                logMessage("Added cascade constraints for table '$tableName', column '{$foreignKey['column']}'", 'INFO');
            } else {
                logMessage("Referenced table or column does not exist for '$tableName' and column '{$foreignKey['column']}'", 'ERROR');
            }
        } catch (PDOException $e) {
            logMessage("Failed to add cascade constraints for table '$tableName', column '{$foreignKey['column']}': " . $e->getMessage(), 'ERROR');
        }
    }
}

function addForeignKeyConstraints(PDO $mariadb, string $tableName, array $foreignKeys): void
{
    foreach ($foreignKeys as $foreignKey) {
        // Validate and set default actions for ON UPDATE and ON DELETE
        $onUpdate = validateAction($foreignKey['on_update'] ?? 'NO ACTION', $foreignKey['name'], $tableName, 'ON UPDATE');
        $onDelete = validateAction($foreignKey['on_delete'] ?? 'NO ACTION', $foreignKey['name'], $tableName, 'ON DELETE');

        // Construct the SQL statement for adding the foreign key constraint
        $constraintSql = "ALTER TABLE `$tableName` ADD CONSTRAINT `{$foreignKey['name']}` FOREIGN KEY (`{$foreignKey['column']}`) REFERENCES `{$foreignKey['referenced_table']}`(`{$foreignKey['referenced_column']}`)";

        if ($onUpdate !== 'NO ACTION') {
            $constraintSql .= " ON UPDATE $onUpdate";
        }
        if ($onDelete !== 'NO ACTION') {
            $constraintSql .= " ON DELETE $onDelete";
        }

        try {
            // Verify the existence of the referenced table and column
            $checkSql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :referenced_table AND COLUMN_NAME = :referenced_column";
            $stmt = $mariadb->prepare($checkSql);
            $stmt->execute([
                ':referenced_table' => $foreignKey['referenced_table'],
                ':referenced_column' => $foreignKey['referenced_column']
            ]);

            if ($stmt->rowCount() > 0) {
                // Ensure the referenced column has an index
                $indexCheckSql = "SHOW INDEX FROM `{$foreignKey['referenced_table']}` WHERE Column_name = :referenced_column";
                $indexStmt = $mariadb->prepare($indexCheckSql);
                $indexStmt->execute([
                    ':referenced_column' => $foreignKey['referenced_column']
                ]);

                if ($indexStmt->rowCount() === 0) {
                    $createIndexSql = "CREATE INDEX idx_{$foreignKey['referenced_column']} ON `{$foreignKey['referenced_table']}`(`{$foreignKey['referenced_column']}`)";
                    $mariadb->exec($createIndexSql);
                }

                // Execute the SQL to add the foreign key constraint
                $mariadb->exec($constraintSql);
                logMessage("Added foreign key constraint for $tableName: {$foreignKey['name']}", 'INFO');
            } else {
                logMessage("Referenced table or column does not exist for $tableName", 'ERROR');
            }
        } catch (PDOException $e) {
            logMessage("Failed to add foreign key constraint for $tableName: {$foreignKey['name']} - " . $e->getMessage(), 'ERROR');
        }
    }
}

function getTableOrder(PDO $pgsql): array
{
    $tables = [];
    $dependencies = [];

    // Get all tables
    $stmt = $pgsql->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get foreign key dependencies
    foreach ($allTables as $table) {
        $query = <<<SQL
            SELECT
                tc.table_name,
                kcu.column_name,
                ccu.table_name AS foreign_table_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_name = :tableName
        SQL;

        $stmt = $pgsql->prepare($query);
        $stmt->execute(['tableName' => $table]);
        $foreignKeys = $stmt->fetchAll();

        $dependencies[$table] = [];
        foreach ($foreignKeys as $fk) {
            $dependencies[$table][] = $fk['foreign_table_name'];
        }

        if (!isset($tables[$table])) {
            $tables[$table] = false; // not processed
        }
    }

    // Helper function to process tables in correct order
    $orderedTables = [];
    $processTable = function ($table) use (&$processTable, &$tables, &$dependencies, &$orderedTables) {
        if ($tables[$table]) { // already processed
            return;
        }

        $tables[$table] = true; // mark as being processed

        // Process dependencies first
        foreach ($dependencies[$table] as $dep) {
            if (isset($tables[$dep]) && !$tables[$dep]) {
                $processTable($dep);
            }
        }

        $orderedTables[] = $table;
    };

    // Process all tables
    foreach ($tables as $table => $processed) {
        if (!$processed) {
            $processTable($table);
        }
    }

    return $orderedTables;
}

class ConsoleOutput
{
    public const COLORS = [
        'green'  => "\033[32m",
        'red'    => "\033[31m",
        'yellow' => "\033[33m",
        'blue'   => "\033[34m",
        'purple' => "\033[35m",
        'cyan'   => "\033[36m",
        'white'  => "\033[37m",
        'reset'  => "\033[0m",
    ];

    public static function showStatus(string $step, string $message, int $current, int $total): void
    {
        if ($total <= 0) {
            $total = 1; // Prevent division by zero
        }

        $percentage = (int)(($current / $total) * 100);
        $progressBar = self::createProgressBar($percentage);

        // Clear screen and move cursor to top
        echo "\033[2J\033[;H";
        echo "=================================================================\n";
        echo "Current Step: " . self::COLORS['cyan'] . $step . self::COLORS['reset'] . "\n";
        echo "Progress: $current/$total ($percentage%)\n";
        echo $progressBar . "\n";
        echo "Current Action: " . self::COLORS['yellow'] . $message . self::COLORS['reset'] . "\n";
        echo "=================================================================\n";

        // Additional logging for detailed tracking
        logMessage("Step: $step, Action: $message, Progress: $current/$total ($percentage%)", 'INFO');

        // Optionally, add a delay for better readability in fast loops
        usleep(50000); // Sleep for 50 milliseconds
    }

    public static function showError(string $message): void
    {
        echo self::COLORS['red'] . "ERROR: $message" . self::COLORS['reset'] . "\n";
    }

    public static function showSuccess(string $message): void
    {
        echo self::COLORS['green'] . "SUCCESS: $message" . self::COLORS['reset'] . "\n";
    }

    public static function showWarning(string $message): void
    {
        echo self::COLORS['yellow'] . "WARNING: $message" . self::COLORS['reset'] . "\n";
    }

    private static function createProgressBar(int $percentage): string
    {
        // Ensure percentage is between 0 and 100
        $percentage = max(0, min(100, $percentage));

        $width = 50;
        $completed = (int)($width * $percentage / 100);
        $remaining = $width - $completed;

        return self::COLORS['green'] .
            str_repeat('█', $completed) .
            self::COLORS['white'] .
            str_repeat('░', $remaining) .
            self::COLORS['reset'];
    }
}

enum DataType: string
{
    case SMALLINT = 'SMALLINT';
    case INTEGER = 'INT';
    case BIGINT = 'BIGINT';
    case BOOLEAN = 'TINYINT(1)';
    case CHARACTER_VARYING = 'VARCHAR(255)';
    case TEXT = 'TEXT';
    case TIMESTAMP = 'DATETIME';
    case DATE = 'DATE';
    case NUMERIC = 'DECIMAL(20,6)';

    public function toMariaDBType(): string
    {
        return match ($this) {
            self::SMALLINT => 'INT',
            self::INTEGER => 'INT',
            self::BIGINT => 'BIGINT',
            self::BOOLEAN => 'TINYINT(1)',
            self::CHARACTER_VARYING => 'VARCHAR(255)',
            self::TEXT => 'TEXT',
            self::TIMESTAMP => 'DATETIME',
            self::DATE => 'DATE',
            self::NUMERIC => 'DECIMAL(20,6)',
        };
    }
}

readonly class DatabaseConfig
{
    public const BATCH_SIZE = 100;
    public const DEFAULT_ENGINE = 'InnoDB';
    public const CHARSET = 'utf8mb4';
    public const COLLATION = 'utf8mb4_unicode_ci';
}

function fetchForeignKeys(PDO $pgsql, string $tableName): array
{
    $query = <<<SQL
        SELECT
            tc.constraint_name,
            kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name,
            tc.constraint_type,
            tc.table_name
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage ccu
            ON ccu.constraint_name = tc.constraint_name
        WHERE tc.constraint_type = 'FOREIGN KEY'
        AND tc.table_name = :tableName
    SQL;

    $stmt = $pgsql->prepare($query);
    $stmt->execute(['tableName' => $tableName]);
    $foreignKeys = $stmt->fetchAll();

    $result = [];
    foreach ($foreignKeys as $fk) {
        $result[] = [
            'name' => $fk['constraint_name'],
            'column' => $fk['column_name'],
            'referenced_table' => $fk['foreign_table_name'],
            'referenced_column' => $fk['foreign_column_name'],
            'on_update' => 'CASCADE',
            'on_delete' => 'CASCADE',
        ];
    }

    return $result;
}

function fetchCascadeConstraints(PDO $pgsql, string $tableName): array
{
    $query = <<<SQL
        SELECT
            tc.constraint_name,
            kcu.column_name,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name,
            tc.constraint_type,
            tc.table_name
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage ccu
            ON ccu.constraint_name = tc.constraint_name
        WHERE tc.constraint_type = 'FOREIGN KEY'
        AND tc.table_name = :tableName
    SQL;

    $stmt = $pgsql->prepare($query);
    $stmt->execute(['tableName' => $tableName]);
    $foreignKeys = $stmt->fetchAll();

    $result = [];
    foreach ($foreignKeys as $fk) {
        $result[] = [
            'name' => $fk['constraint_name'],
            'column' => $fk['column_name'],
            'referenced_table' => $fk['foreign_table_name'],
            'referenced_column' => $fk['foreign_column_name'],
        ];
    }

    return $result;
}

const COLORS = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'cyan' => "\033[36m",
    'white' => "\033[37m",
];

function colorize(string $message, string $color): string
{
    return COLORS[$color] . $message . COLORS['reset'];
}

function displayWarning(): void
{
    echo colorize("############################ WARNING ################################\n", 'red');
    echo colorize("This script is provided as-is, and you use it at your own risk.\n", 'yellow');
    echo colorize("The author disclaims any liability for damages caused by its use.\n", 'yellow');
    echo colorize("This script migrates data from PostgreSQL to MariaDB.\n", 'yellow');
    echo colorize("PLEASE READ THE README.MD FILE BEFORE RUNNING THIS SCRIPT.\n", 'yellow');
    echo colorize("Ensure all necessary backups are taken before proceeding.\n", 'yellow');
    echo colorize("######################################################################\n", 'red');
    echo colorize("Type 'YES' to confirm you have read and understood the warning: ", 'cyan');
    $confirmation = trim(fgets(STDIN));
    if (strtoupper($confirmation) !== 'YES') {
        exit("Operation cancelled by user.\n");
    }
}

function profileStart(string $section): void
{
    logMessage("Profiling start: $section", 'DEBUG');
}

function profileEnd(string $section): void
{
    logMessage("Profiling end: $section", 'DEBUG');
}

function main()
{
    logMessage("Migration started.", 'INFO');
    displayWarning();
    try {
        // Load environment variables
        loadEnv(__DIR__ . '/.env');

        // Create database connections using individual environment variables
        $pgsql = createPDOConnection(getenv('PGSQL_HOST'), getenv('PGSQL_PORT'), getenv('PGSQL_DBNAME'), getenv('PGSQL_USER'), getenv('PGSQL_PASSWORD'), 'pgsql');
        $mariadb = createPDOConnection(getenv('MARIADB_HOST'), getenv('MARIADB_PORT'), getenv('MARIADB_DBNAME'), getenv('MARIADB_USER'), getenv('MARIADB_PASSWORD'), 'mysql');

        // Perform migration
        migrateDatabase($pgsql, $mariadb);

        // Drop audit tables
        $tableNames = getTableOrder($pgsql);

        logMessage("Migration completed successfully.", 'SUCCESS');
        echo colorize("Migration completed successfully. Check the log for details.\n", 'green');
    } catch (Exception $e) {
        logMessage("Migration failed: " . $e->getMessage(), 'ERROR');
        echo colorize("Migration failed. Check the log for details.\n", 'red');
    }
}

// Check if required PHP extensions are loaded
$requiredExtensions = ['pdo_pgsql', 'pdo_mysql', 'pdo'];
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        ConsoleOutput::showError("Missing required extension: $extension");
        logMessage("Missing required extension: $extension.", 'ERROR');
        exit(1);
    }
}

main();
