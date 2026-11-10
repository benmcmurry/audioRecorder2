<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/feedback.php';

ar_feedback_require_admin();
$report = ar_feedback_find(trim((string) ($_GET['id'] ?? '')));
$path = $report ? ar_feedback_screenshot_absolute_path($report) : null;
if (!$path) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
