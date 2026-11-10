<?php
require_once dirname(__DIR__) . '/includes/feedback.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$db = ar_include_db();
$statement = $db->prepare("SELECT id, screenshot_path FROM Feedback_reports WHERE screenshot_path IS NOT NULL AND status IN ('resolved', 'dismissed') AND resolved_at IS NOT NULL AND resolved_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
if (!$statement) {
    fwrite(STDERR, "Unable to prepare screenshot cleanup query.\n");
    exit(1);
}
$statement->execute();
$result = $statement->get_result();
$reports = array();
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}
$statement->close();

$removed = 0;
foreach ($reports as $report) {
    $relative = (string) $report['screenshot_path'];
    if (!preg_match('#^\d{4}/\d{2}/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.png$#i', $relative)) {
        continue;
    }
    $path = ar_feedback_screenshot_absolute_path($report);
    if ($path && is_file($path) && !@unlink($path)) {
        fwrite(STDERR, 'Could not remove screenshot for report ' . $report['id'] . ".\n");
        continue;
    }
    $clear = $db->prepare('UPDATE Feedback_reports SET screenshot_path = NULL, screenshot_sha256 = NULL, screenshot_bytes = NULL WHERE id = ?');
    if (!$clear) {
        fwrite(STDERR, 'Could not clear screenshot metadata for report ' . $report['id'] . ".\n");
        continue;
    }
    $clear->bind_param('s', $report['id']);
    if ($clear->execute()) { $removed++; }
    else { fwrite(STDERR, 'Could not clear screenshot metadata for report ' . $report['id'] . ".\n"); }
    $clear->close();
}

echo 'Removed ' . $removed . " resolved or dismissed feedback screenshot(s).\n";
