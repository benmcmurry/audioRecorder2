<?php
include_once('../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('responseHelpers.php');

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    ar_download_error(400, 'Missing download parameters.');
}

$responseId = (int) $_GET['id'];
$type = $_GET['type'];
if ($responseId <= 0 || ($type !== 'audio' && $type !== 'transcript')) {
    ar_download_error(400, 'Invalid download parameters.');
}

$row = ar_response_for_download($elc_db, $responseId);

if (!$row) {
    ar_download_error(404, 'Response not found.');
}

if ($row['prompt_owner'] !== $netid) {
    ar_download_error(403, 'You do not have access to this response.');
}

$studentName = ar_student_name($row);
$baseName = 'prompt_' . ar_safe_file_name($row['prompt_id'], 'prompt') . '_' . ar_safe_file_name($studentName, 'student');

if ($type === 'transcript') {
    $filename = $baseName . '_transcript.txt';
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $row['transcription_text'];
    exit;
}

$audioPath = ar_audio_file_path($row['filename']);
if (!$audioPath) {
    ar_download_error(404, 'Audio file not found.');
}

$extension = pathinfo($audioPath, PATHINFO_EXTENSION);
$filename = $baseName . ($extension ? '.' . $extension : '');
$filetype = $row['filetype'] ? $row['filetype'] : 'application/octet-stream';

header('Content-Type: ' . $filetype);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($audioPath));
readfile($audioPath);
exit;
