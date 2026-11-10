<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function ar_openai_activity_migration_usage($exitCode = 0)
{
    $message = "Usage: php scripts/migrate_openai_activity.php --expected-db=DATABASE [--apply]\n"
        . "Without --apply, the script verifies the database and reports the migration without changing it.\n"
        . "Back up the database before applying the migration in production.\n";
    fwrite($exitCode === 0 ? STDOUT : STDERR, $message);
    exit($exitCode);
}

$expectedDatabase = '';
$applyMigration = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        ar_openai_activity_migration_usage(0);
    }
    if ($argument === '--apply') {
        $applyMigration = true;
        continue;
    }
    if (strncmp($argument, '--expected-db=', 14) === 0) {
        $expectedDatabase = substr($argument, 14);
        continue;
    }

    fwrite(STDERR, "Unknown option: " . $argument . "\n");
    ar_openai_activity_migration_usage(2);
}

if ($expectedDatabase === '' || !preg_match('/\A[A-Za-z0-9_-]+\z/', $expectedDatabase)) {
    fwrite(STDERR, "Provide the expected database name explicitly.\n");
    ar_openai_activity_migration_usage(2);
}

$privateRoot = getenv('APP_PRIVATE_ROOT');
if ($privateRoot === false || trim((string) $privateRoot) === '' || !is_dir((string) $privateRoot)) {
    $privateRoot = dirname(__DIR__, 3) . '/private-config';
}
$connectionFile = rtrim(trim((string) $privateRoot), '/') . '/connectFiles/connect_ar.php';
$migrationPath = dirname(__DIR__) . '/sql/2026-11-10_openai_activity_log.sql';
if (!is_readable($connectionFile) || !is_readable($migrationPath)) {
    fwrite(STDERR, "Database configuration or migration file is unavailable.\n");
    exit(2);
}

try {
    if (!isset($_SERVER['REMOTE_ADDR'])) {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }
    require_once $connectionFile;
    if (!isset($elc_db) || !($elc_db instanceof mysqli)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $databaseResult = $elc_db->query('SELECT DATABASE()');
    $databaseRow = $databaseResult ? $databaseResult->fetch_row() : null;
    $actualDatabase = isset($databaseRow[0]) ? (string) $databaseRow[0] : '';
    if ($actualDatabase === '' || strcasecmp($actualDatabase, $expectedDatabase) !== 0) {
        fwrite(STDERR, "Connected database does not match --expected-db. No migration was run.\n");
        exit(3);
    }

    $migrationSql = file_get_contents($migrationPath);
    if (!is_string($migrationSql) || trim($migrationSql) === '') {
        throw new RuntimeException('Migration file is empty.');
    }

    if (!$applyMigration) {
        echo "Dry run passed for database `" . $actualDatabase . "`. No changes were made.\n";
        echo "Would create OpenAI_activity_log for per-request API audit records.\n";
        echo "Back up production before rerunning with --apply.\n";
        exit(0);
    }

    if (!$elc_db->query($migrationSql)) {
        throw new RuntimeException('Migration query failed.');
    }

    $tableResult = $elc_db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'OpenAI_activity_log'");
    $tableRow = $tableResult ? $tableResult->fetch_row() : null;
    if (!$tableRow || (int) $tableRow[0] !== 1) {
        throw new RuntimeException('The activity table is missing after migration.');
    }

    echo "OpenAI activity migration completed and verified for database `" . $actualDatabase . "`.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Migration failed. Check database connectivity and migration state; the migration is safe to rerun.\n");
    exit(1);
}

?>
