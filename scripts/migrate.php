#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc > 1) {
    fwrite(STDERR, "This runner applies every pending migration. Do not pass a migration filename.\n");
    exit(1);
}

function migrate_out(string $message): void
{
    fwrite(STDOUT, '[migrate] ' . $message . PHP_EOL);
}

function migrate_error(string $message): void
{
    fwrite(STDERR, '[migrate] Error: ' . $message . PHP_EOL);
}

function migrate_files(): array
{
    $files = glob(dirname(__DIR__) . '/sql/*.sql') ?: array();
    sort($files, SORT_NATURAL);
    return $files;
}

function migrate_version(string $path): string
{
    return pathinfo($path, PATHINFO_FILENAME);
}

function migrate_ensure_tracking_table(mysqli $db): void
{
    $sql = 'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(128) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if (!$db->query($sql)) {
        throw new RuntimeException('Could not create schema_migrations: ' . $db->error);
    }
}

function migrate_applied_versions(mysqli $db): array
{
    $result = $db->query('SELECT version FROM schema_migrations');
    if (!$result) {
        throw new RuntimeException('Could not read schema_migrations: ' . $db->error);
    }
    $applied = array();
    while ($row = $result->fetch_assoc()) {
        $applied[(string) $row['version']] = true;
    }
    $result->free();
    return $applied;
}

function migrate_schema_object_exists(mysqli $db, string $kind, string $table, string $object): bool
{
    $queries = array(
        'column' => 'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
        'index' => 'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
        'table' => 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
    );
    if (!isset($queries[$kind])) {
        throw new RuntimeException('Unsupported schema object check.');
    }

    $statement = $db->prepare($queries[$kind]);
    if (!$statement) {
        throw new RuntimeException('Could not prepare schema check: ' . $db->error);
    }
    if ($kind === 'table') {
        $statement->bind_param('s', $table);
    } else {
        $statement->bind_param('ss', $table, $object);
    }
    if (!$statement->execute()) {
        throw new RuntimeException('Could not check database schema: ' . $statement->error);
    }
    $statement->store_result();
    $exists = $statement->num_rows > 0;
    $statement->close();
    return $exists;
}

function migrate_audio_processing_schema_state(mysqli $db): string
{
    $columns = array(
        'submission_id',
        'status',
        'transcription_status',
        'transcription_error',
        'transcription_source',
        'processing_started_at',
        'processing_finished_at',
    );
    $indexes = array(
        'uq_audio_files_submission_id',
        'idx_audio_files_status_created',
        'idx_audio_files_processing_started_at',
    );
    $present = 0;
    foreach ($columns as $column) {
        $present += migrate_schema_object_exists($db, 'column', 'Audio_files', $column) ? 1 : 0;
    }
    foreach ($indexes as $index) {
        $present += migrate_schema_object_exists($db, 'index', 'Audio_files', $index) ? 1 : 0;
    }

    if ($present === 0) {
        return 'absent';
    }
    return $present === count($columns) + count($indexes) ? 'complete' : 'partial';
}

function migrate_legacy_schema_is_complete(mysqli $db, string $version): bool
{
    if ($version === '2026-06-03_audio_file_processing') {
        return migrate_audio_processing_schema_state($db) === 'complete';
    }
    if ($version === '2026-10-30_feedback_reports') {
        if (!migrate_schema_object_exists($db, 'table', 'AudioRecorder_admins', '')
            || !migrate_schema_object_exists($db, 'table', 'Feedback_reports', '')) {
            return false;
        }
        $result = $db->query("SELECT 1 FROM AudioRecorder_admins WHERE netid = 'blm39' LIMIT 1");
        if (!$result) {
            throw new RuntimeException('Could not verify the existing feedback reviewer: ' . $db->error);
        }
        $present = $result->num_rows > 0;
        $result->free();
        return $present;
    }
    if ($version === '2026-11-10_openai_activity_log') {
        return migrate_schema_object_exists($db, 'table', 'OpenAI_activity_log', '');
    }
    return false;
}

function migrate_apply_file(mysqli $db, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Unable to read migration file: ' . $path);
    }

    // The project migrations contain plain DDL statements separated by semicolons.
    $sql = preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/m', '', $sql) ?? $sql;
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: array();
    foreach (array_filter(array_map('trim', $statements), static function (string $statement): bool {
        return $statement !== '';
    }) as $statement) {
        if (!$db->query($statement)) {
            throw new RuntimeException('Migration statement failed: ' . $db->error);
        }
    }
}

function migrate_record(mysqli $db, string $version): void
{
    $statement = $db->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, NOW())');
    if (!$statement) {
        throw new RuntimeException('Could not prepare migration record: ' . $db->error);
    }
    $statement->bind_param('s', $version);
    if (!$statement->execute()) {
        throw new RuntimeException('Could not record migration ' . $version . ': ' . $statement->error);
    }
    $statement->close();
}

try {
    $privateRoot = getenv('APP_PRIVATE_ROOT');
    $privateRoot = is_string($privateRoot) && trim($privateRoot) !== ''
        ? rtrim(trim($privateRoot), '/')
        : dirname(__DIR__, 3) . '/private-config';
    $connectPath = $privateRoot . '/connectFiles/connect_ar.php';
    if (!is_readable($connectPath)) {
        throw new RuntimeException('Database configuration is unavailable at ' . $connectPath . '.');
    }

    if (!isset($_SERVER['REMOTE_ADDR'])) {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }
    require $connectPath;
    if (!isset($elc_db) || !$elc_db instanceof mysqli) {
        throw new RuntimeException('The Audio Recorder database connection is unavailable.');
    }
    $db = $elc_db;

    $files = migrate_files();
    if ($files === array()) {
        migrate_out('No migration files found in sql/.');
        exit(0);
    }

    migrate_ensure_tracking_table($db);
    $applied = migrate_applied_versions($db);
    $completed = 0;
    $skipped = 0;

    foreach ($files as $file) {
        $version = migrate_version($file);
        if (isset($applied[$version])) {
            migrate_out('Skipping already applied ' . basename($file));
            $skipped++;
            continue;
        }

        if ($version === '2026-06-03_audio_file_processing') {
            $audioSchemaState = migrate_audio_processing_schema_state($db);
            if ($audioSchemaState === 'partial') {
                throw new RuntimeException('The existing Audio_files processing schema is incomplete. No statements from ' . basename($file) . ' were run; reconcile its columns and indexes before retrying.');
            }
        }

        if (migrate_legacy_schema_is_complete($db, $version)) {
            migrate_record($db, $version);
            $applied[$version] = true;
            migrate_out('Recorded existing schema for ' . basename($file));
            $skipped++;
            continue;
        }

        migrate_out('Applying ' . basename($file));
        migrate_apply_file($db, $file);
        migrate_record($db, $version);
        $applied[$version] = true;
        migrate_out('Applied ' . basename($file));
        $completed++;
    }

    migrate_out('Completed. ' . $completed . ' applied; ' . $skipped . ' already complete.');
    exit(0);
} catch (Throwable $error) {
    migrate_error($error->getMessage());
    exit(1);
}
