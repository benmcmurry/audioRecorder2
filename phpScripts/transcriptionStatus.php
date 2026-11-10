<?php
include_once(__DIR__ . '/../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once(__DIR__ . '/audioProcessingCommon.php');
include_once(__DIR__ . '/responseHelpers.php');

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
header('Cache-Control: no-store, private');

$netid = isset($netid) ? trim((string) $netid) : '';
if ($netid === '') {
    ar_json_response(401, array('ok' => false, 'message' => 'Sign in is required.'));
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$responseIdInput = $method === 'POST' ? ($_POST['response_id'] ?? '') : ($_GET['response_id'] ?? '');
$responseId = trim((string) $responseIdInput);
if (!preg_match('/\A[0-9]+\z/', $responseId) || (int) $responseId < 1) {
    ar_json_response(400, array('ok' => false, 'message' => 'Invalid recording.'));
}

if ($method === 'POST' && !ar_valid_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    ar_json_response(403, array('ok' => false, 'message' => 'Your session expired. Reload the page and try again.'));
}
if (!in_array($method, array('GET', 'POST'), true)) {
    ar_json_response(405, array('ok' => false, 'message' => 'Method not allowed.'));
}

$query = $elc_db->prepare('SELECT id, prompt_id, netid, filename, transcription_status, transcription_text, transcription_source, submission_id FROM Audio_files WHERE id = ? AND netid = ? LIMIT 1');
if (!$query) {
    ar_json_response(500, array('ok' => false, 'message' => 'Could not check transcription status.'));
}
$responseIdInt = (int) $responseId;
$query->bind_param('is', $responseIdInt, $netid);
if (!$query->execute()) {
    ar_json_response(500, array('ok' => false, 'message' => 'Could not check transcription status.'));
}
$result = $query->get_result();
$recording = $result ? $result->fetch_assoc() : null;
if (!$recording) {
    ar_json_response(404, array('ok' => false, 'message' => 'Recording not found.'));
}

if ($method === 'POST') {
    if ((string) $recording['transcription_status'] !== 'failed') {
        ar_json_response(409, array('ok' => false, 'message' => 'This transcript is not available to retry.'));
    }
    $audioPath = ar_audio_file_path($recording['filename']);
    if (!$audioPath) {
        ar_json_response(422, array('ok' => false, 'message' => 'The saved audio file is unavailable, so transcription cannot be retried.'));
    }
    if (trim((string) $recording['submission_id']) === '') {
        ar_json_response(422, array('ok' => false, 'message' => 'This recording cannot be retried automatically. Please contact support.'));
    }
    if (php_sapi_name() === 'cli' || !function_exists('proc_open')) {
        ar_json_response(503, array('ok' => false, 'message' => 'Transcription retry is unavailable right now. Please try again later.'));
    }

    $reset = $elc_db->prepare("UPDATE Audio_files SET status = 'uploaded', transcription_status = 'pending', transcription_error = NULL, transcription_source = 'queue', processing_started_at = NULL, processing_finished_at = NULL WHERE id = ? AND netid = ? AND status = 'failed_transcription' AND transcription_status = 'failed'");
    if (!$reset) {
        ar_json_response(500, array('ok' => false, 'message' => 'Could not queue transcription retry.'));
    }
    $reset->bind_param('is', $responseIdInt, $netid);
    if (!$reset->execute()) {
        ar_json_response(500, array('ok' => false, 'message' => 'Could not queue transcription retry.'));
    }
    if ($reset->affected_rows !== 1) {
        ar_json_response(409, array('ok' => false, 'message' => 'This transcript has already changed. Refresh the page and try again.'));
    }

    if (!ar_kick_audio_worker((string) $recording['submission_id'])) {
        $restore = $elc_db->prepare("UPDATE Audio_files SET status = 'failed_transcription', transcription_status = 'failed', transcription_error = 'Transcription retry could not be started.', transcription_source = 'queue', processing_finished_at = NOW() WHERE id = ? AND netid = ? AND status = 'uploaded' AND transcription_status = 'pending'");
        if ($restore) {
            $restore->bind_param('is', $responseIdInt, $netid);
            $restore->execute();
        }
        ar_json_response(503, array('ok' => false, 'message' => 'The retry could not be started. Please try again later.'));
    }

    ar_json_response(200, array('ok' => true, 'transcription_status' => 'pending'));
}

$status = (string) $recording['transcription_status'];
$payload = array('ok' => true, 'transcription_status' => $status);
if ($status === 'complete') {
    $payload['transcription_text'] = (string) $recording['transcription_text'];
    $payload['transcription_source'] = (string) $recording['transcription_source'];
}
ar_json_response(200, $payload);
