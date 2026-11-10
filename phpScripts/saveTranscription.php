<?php
include_once('../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
if (!ar_valid_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    http_response_code(403);
    exit('Your session expired. Reload the page and try again.');
}

$promptId = isset($_POST['prompt_id']) ? trim((string) $_POST['prompt_id']) : '';
$transcription = isset($_POST['transcription']) ? (string) $_POST['transcription'] : '';
$netid = isset($netid) ? trim((string) $netid) : '';
if ($netid === '') {
    http_response_code(401);
    exit('Sign in is required.');
}
if (!preg_match('/\\A[0-9]+\\z/', $promptId)) {
    http_response_code(400);
    exit('Invalid prompt.');
}

$query = $elc_db->prepare("UPDATE Audio_files SET transcription_text = ?, transcription_status = 'complete', transcription_source = 'manual', status = 'complete', transcription_error = NULL, processing_finished_at = NOW() WHERE prompt_id = ? AND netid = ?");
if (!$query) {
    http_response_code(500);
    exit('Could not save the transcript.');
}
$query->bind_param('sss', $transcription, $promptId, $netid);
if (!$query->execute()) {
    http_response_code(500);
    exit('Could not save the transcript.');
}

echo 'Saving . . .';
?>
