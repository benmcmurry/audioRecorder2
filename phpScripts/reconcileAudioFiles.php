<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(405);
    echo "This endpoint is intended for CLI use only.";
    exit;
}

include_once(dirname(__FILE__) . '/../../../connectFiles/connect_ar.php');

$missingFiles = array();
$query = $elc_db->query("SELECT id, filename, status FROM Audio_files WHERE filename IS NOT NULL AND filename <> ''");
while ($query && ($row = $query->fetch_assoc())) {
    $appRoot = realpath(dirname(__FILE__) . '/..');
    $audioPath = realpath($appRoot . '/' . ltrim((string) $row['filename'], '/'));
    if (!$audioPath || !is_file($audioPath)) {
        $missingFiles[] = $row['id'];
    }
}

foreach ($missingFiles as $id) {
    $stmt = $elc_db->prepare("UPDATE Audio_files SET status = 'failed_transcription', transcription_status = 'failed', transcription_source = 'queue', transcription_error = 'Audio file missing on disk.', processing_finished_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

$stuckQuery = $elc_db->query("SELECT id FROM Audio_files WHERE status = 'transcribing' AND (processing_started_at IS NULL OR processing_started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE))");
while ($stuckQuery && ($row = $stuckQuery->fetch_assoc())) {
    $stmt = $elc_db->prepare("UPDATE Audio_files SET status = 'uploaded', transcription_status = 'pending', transcription_error = NULL, processing_started_at = NULL, processing_finished_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $row['id']);
    $stmt->execute();
}

echo "Reconciled " . count($missingFiles) . " missing file(s)." . PHP_EOL;
