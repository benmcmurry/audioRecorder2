<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

require_once dirname(__DIR__) . '/includes/feedback.php';

try {
    $db = ar_include_db();
    $result = $db->query('DELETE FROM OpenAI_activity_log WHERE attempted_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 90 DAY)');
    if (!$result) {
        throw new RuntimeException('Could not remove expired activity entries.');
    }

    echo 'Removed ' . (int) $db->affected_rows . " expired OpenAI activity entry/entries.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "OpenAI activity cleanup failed. Confirm the database connection and migration.\n");
    exit(1);
}

?>
