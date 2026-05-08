<?php
include_once('../cas-go.php');
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');

function ar_download_error($statusCode, $message)
{
    http_response_code($statusCode);
    echo $message;
    exit;
}

function ar_safe_download_name($value, $fallback)
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $value);
    $value = trim($value, '_');
    return $value === '' ? $fallback : $value;
}

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    ar_download_error(400, 'Missing download parameters.');
}

$responseId = (int) $_GET['id'];
$type = $_GET['type'];
if ($responseId <= 0 || ($type !== 'audio' && $type !== 'transcript')) {
    ar_download_error(400, 'Invalid download parameters.');
}

$query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name, Prompts.netid AS prompt_owner FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.id = ? LIMIT 1");
$query->bind_param("i", $responseId);
$query->execute();
$result = $query->get_result();
$row = $result ? $result->fetch_assoc() : null;

if (!$row) {
    ar_download_error(404, 'Response not found.');
}

if ($row['prompt_owner'] !== $netid) {
    ar_download_error(403, 'You do not have access to this response.');
}

$studentName = $row['user_name'] ? $row['user_name'] : $row['netid'];
$baseName = 'prompt_' . ar_safe_download_name($row['prompt_id'], 'prompt') . '_' . ar_safe_download_name($studentName, 'student');

if ($type === 'transcript') {
    $filename = $baseName . '_transcript.txt';
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $row['transcription_text'];
    exit;
}

$appRoot = realpath(__DIR__ . '/..');
$audioPath = realpath($appRoot . '/' . $row['filename']);
if (!$audioPath || strpos($audioPath, $appRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($audioPath)) {
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
