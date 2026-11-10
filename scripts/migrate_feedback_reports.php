<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function ar_feedback_migration_usage($exitCode = 0)
{
    $message = "Usage: php scripts/migrate_feedback_reports.php --expected-db=DATABASE [--apply]\n"
        . "Without --apply, the script checks the connection and reports what it would migrate.\n"
        . "Take a database backup before using --apply on production.\n";
    fwrite($exitCode === 0 ? STDOUT : STDERR, $message);
    exit($exitCode);
}

$expectedDatabase = '';
$applyMigration = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        ar_feedback_migration_usage(0);
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
    ar_feedback_migration_usage(2);
}

if ($expectedDatabase === '' || !preg_match('/\\A[A-Za-z0-9_-]+\\z/', $expectedDatabase)) {
    fwrite(STDERR, "Provide the expected database name explicitly.\n");
    ar_feedback_migration_usage(2);
}

$privateRoot = getenv('APP_PRIVATE_ROOT');
if ($privateRoot === false || trim((string) $privateRoot) === '' || !is_dir((string) $privateRoot)) {
    $privateRoot = dirname(__DIR__, 3) . '/private-config';
}
$connectionFile = rtrim(trim((string) $privateRoot), '/') . '/connectFiles/connect_ar.php';
if (!is_readable($connectionFile)) {
    fwrite(STDERR, "Database configuration is unavailable. Set APP_PRIVATE_ROOT to the private config directory.\n");
    exit(2);
}

if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

try {
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

    $migrationPath = dirname(__DIR__) . '/sql/2026-10-30_feedback_reports.sql';
    if (!is_readable($migrationPath)) {
        throw new RuntimeException('Feedback migration file is unavailable.');
    }
    $migrationSql = file_get_contents($migrationPath);
    if (!is_string($migrationSql) || trim($migrationSql) === '') {
        throw new RuntimeException('Feedback migration file is empty.');
    }

    if (!$applyMigration) {
        echo "Dry run passed for database `" . $actualDatabase . "`. No changes were made.\n";
        echo "Would apply sql/2026-10-30_feedback_reports.sql (reviewer table, reports table, initial reviewer seed).\n";
        echo "Back up production before rerunning with --apply.\n";
        exit(0);
    }

    if (!$elc_db->multi_query($migrationSql)) {
        throw new RuntimeException('The migration could not be started.');
    }

    do {
        $statementResult = $elc_db->store_result();
        if ($statementResult instanceof mysqli_result) {
            $statementResult->free();
        }
        if (!$elc_db->more_results()) {
            break;
        }
        if (!$elc_db->next_result()) {
            throw new RuntimeException('A migration statement failed.');
        }
    } while (true);

    $tableResult = $elc_db->query('SHOW TABLES');
    if (!$tableResult) {
        throw new RuntimeException('Could not verify the feedback tables.');
    }
    $foundTables = array();
    while ($tableRow = $tableResult->fetch_row()) {
        if (in_array($tableRow[0], array('AudioRecorder_admins', 'Feedback_reports'), true)) {
            $foundTables[$tableRow[0]] = true;
        }
    }
    if (count($foundTables) !== 2) {
        throw new RuntimeException('One or more feedback tables are missing after migration.');
    }

    $seedCheck = $elc_db->prepare('SELECT netid FROM AudioRecorder_admins WHERE netid = ? LIMIT 1');
    if (!$seedCheck) {
        throw new RuntimeException('Could not verify the initial reviewer.');
    }
    $initialReviewer = 'blm39';
    $seedCheck->bind_param('s', $initialReviewer);
    $seedCheck->execute();
    $seedCheck->store_result();
    if ($seedCheck->num_rows < 1) {
        throw new RuntimeException('The initial reviewer seed is missing.');
    }

    echo "Feedback migration completed and verified for database `" . $actualDatabase . "`.\n";
    echo "Both feedback tables and the initial reviewer seed are present.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Migration failed. Check database connectivity and migration state; the migration is safe to rerun.\n");
    exit(1);
}

?>
