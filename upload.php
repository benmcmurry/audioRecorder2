<?php
include_once(__DIR__ . '/cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 2) . '/private-config') . '/connectFiles/connect_ar.php');
include_once(__DIR__ . '/phpScripts/audioProcessingCommon.php');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

define('SITE_ROOT', realpath(dirname(__FILE__)));

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ar_json_response(405, array('ok' => false, 'message' => 'Method not allowed.'));
}
if (!ar_valid_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    ar_json_response(403, array('ok' => false, 'message' => 'Your session expired. Reload the page and try again.'));
}

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (ob_get_length()) {
        @ob_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'message' => 'Upload failed unexpectedly.',
        'details' => isset($error['message']) ? $error['message'] : 'Unknown fatal error.',
    ));
});

function ar_kick_audio_worker($submissionId)
{
    if (php_sapi_name() === 'cli' || !function_exists('proc_open')) {
        ar_audio_log('worker_kick_skipped', array(
            'submission_id' => $submissionId,
            'reason' => php_sapi_name() === 'cli' ? 'cli_context' : 'proc_open_unavailable',
        ));
        return;
    }

    $worker = escapeshellarg(__DIR__ . '/phpScripts/processPendingAudio.php');
    $phpBinary = escapeshellarg(ar_php_cli_binary());
    $submissionArg = escapeshellarg($submissionId);
    $command = $phpBinary . ' ' . $worker . ' ' . $submissionArg . ' > /dev/null 2>&1 &';
    ar_audio_log('worker_kick_attempt', array(
        'submission_id' => $submissionId,
        'php_binary' => ar_php_cli_binary(),
        'worker_script' => __DIR__ . '/phpScripts/processPendingAudio.php',
    ));
    $descriptors = array(
        0 => array('file', '/dev/null', 'r'),
        1 => array('file', '/dev/null', 'a'),
        2 => array('file', '/dev/null', 'a'),
    );
    $process = @proc_open($command, $descriptors, $pipes, __DIR__, ar_worker_environment());
    $exitCode = is_resource($process) ? proc_close($process) : null;
    ar_audio_log('worker_kick_result', array(
        'submission_id' => $submissionId,
        'started' => is_int($exitCode) && $exitCode === 0,
        'exit_code' => $exitCode,
    ));
}

$targetdir = '/uploads/';
$submissionId = isset($_POST['submission_id']) ? (string) $_POST['submission_id'] : '';
$promptId = isset($_POST['prompt_id']) ? (string) $_POST['prompt_id'] : '';
$extension = isset($_POST['extension']) ? (string) $_POST['extension'] : '';
$netid = isset($netid) ? trim((string) $netid) : '';
$uploadedFile = isset($_FILES['myBlob']) && is_array($_FILES['myBlob']) ? $_FILES['myBlob'] : null;
$uploadsPath = SITE_ROOT . $targetdir;

if ($netid === '') {
    ar_json_response(401, array(
        'ok' => false,
        'message' => 'Sign in is required to upload a recording.'
    ));
}

if (!preg_match('/\A[0-9]+\z/', $promptId)
    || !preg_match('/\A[A-Za-z0-9_-]{8,64}\z/', $submissionId)
    || !in_array($extension, array('webm', 'mp4'), true)
    || !$uploadedFile
    || !isset($uploadedFile['error'], $uploadedFile['tmp_name'])
    || (int) $uploadedFile['error'] !== UPLOAD_ERR_OK
    || !is_uploaded_file($uploadedFile['tmp_name'])) {
    ar_json_response(400, array(
        'ok' => false,
        'message' => 'The recording upload is invalid or incomplete.'
    ));
}

$promptQuery = $elc_db->prepare("SELECT prompt_id, transcription, archive FROM Prompts WHERE prompt_id = ? LIMIT 1");
if (!$promptQuery) {
    ar_json_response(500, array('ok' => false, 'message' => 'Could not validate the prompt.'));
}
$promptQuery->bind_param('s', $promptId);
$promptQuery->execute();
$promptResult = $promptQuery->get_result();
$promptRow = $promptResult ? $promptResult->fetch_assoc() : null;
if (!$promptRow || (int) $promptRow['archive'] !== 0) {
    ar_json_response(404, array('ok' => false, 'message' => 'This prompt is unavailable.'));
}
$promptTranscriptionRequired = isset($promptRow['transcription']) ? (int) $promptRow['transcription'] : 0;

if (!is_dir($uploadsPath)) {
    @mkdir($uploadsPath, 0755, true);
}

if (!is_dir($uploadsPath) || !is_writable($uploadsPath)) {
    http_response_code(500);
    echo "Upload directory is missing or not writable.";
    exit;
}

$safeExtension = $extension === 'mp4' ? 'mp4' : 'webm';
$storedFileName = 'recording_' . (int) $promptId . '_' . bin2hex(random_bytes(12)) . '.' . $safeExtension;
$targetFile = $uploadsPath . $storedFileName;
$fileLocation = "uploads/" . $storedFileName;
$storedFileType = $safeExtension === 'mp4' ? 'audio/mp4' : 'audio/webm';

$existingQuery = $elc_db->prepare("SELECT id, prompt_id, netid, filename, filesize, filetype, transcription_text, status, transcription_status, transcription_error, transcription_source, submission_id FROM Audio_files WHERE submission_id = ? LIMIT 1");
if ($existingQuery) {
    $existingQuery->bind_param("s", $submissionId);
    $existingQuery->execute();
    $existingResult = $existingQuery->get_result();
    $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
    if ($existingRow) {
        if ((string) $existingRow['netid'] !== $netid || (string) $existingRow['prompt_id'] !== $promptId) {
            ar_json_response(409, array(
                'ok' => false,
                'message' => 'This submission ID is already in use.'
            ));
        }
        ar_audio_log('upload_duplicate_submission', array(
            'submission_id' => $submissionId,
            'prompt_id' => $promptId,
            'netid' => $netid,
            'existing_status' => isset($existingRow['status']) ? $existingRow['status'] : null,
            'existing_transcription_status' => isset($existingRow['transcription_status']) ? $existingRow['transcription_status'] : null,
        ));
        ar_kick_audio_worker($submissionId);
        ar_json_response(200, array(
            'ok' => true,
            'message' => 'Your response was already saved.',
            'submission_id' => $submissionId,
            'status' => isset($existingRow['status']) ? $existingRow['status'] : 'uploaded',
            'transcription_status' => isset($existingRow['transcription_status']) ? $existingRow['transcription_status'] : 'pending',
            'transcription_text' => isset($existingRow['transcription_text']) ? $existingRow['transcription_text'] : '',
            'transcription_error' => isset($existingRow['transcription_error']) ? $existingRow['transcription_error'] : '',
            'transcription_source' => isset($existingRow['transcription_source']) ? $existingRow['transcription_source'] : 'queue'
        ));
    }
}

if (move_uploaded_file($uploadedFile['tmp_name'], $targetFile)) {
    ar_audio_log('upload_file_moved', array(
        'submission_id' => $submissionId,
        'prompt_id' => $promptId,
        'netid' => $netid,
        'target_file' => $targetFile,
        'file_location' => $fileLocation,
        'filesize' => isset($uploadedFile['size']) ? (int) $uploadedFile['size'] : null,
    ));
    $transcriptionText = "";
    $transcriptionError = "";
    $transcriptionStatus = "pending";
    $status = "uploaded";
    $query = $elc_db->prepare("INSERT INTO Audio_files (prompt_id, netid, filename, filesize, filetype, transcription_text, submission_id, status, transcription_status, transcription_error, transcription_source, processing_started_at, processing_finished_at, date_created) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,NULL,NOW())");
    if (!$query) {
        ar_audio_log('upload_prepare_failed', array(
            'submission_id' => $submissionId,
            'prompt_id' => $promptId,
            'netid' => $netid,
            'db_error' => $elc_db->error,
        ));
        @unlink($targetFile);
        ar_json_response(500, array(
            'ok' => false,
            'message' => 'Could not prepare recording insert.',
            'details' => $elc_db->error
        ));
    }
    $filesize = isset($uploadedFile['size']) ? (string) $uploadedFile['size'] : '0';
    $transcriptionSource = 'queue';
    if (!$query->bind_param("sssssssssss", $promptId, $netid, $fileLocation, $filesize, $storedFileType, $transcriptionText, $submissionId, $status, $transcriptionStatus, $transcriptionError, $transcriptionSource)) {
        ar_audio_log('upload_bind_failed', array(
            'submission_id' => $submissionId,
            'prompt_id' => $promptId,
            'netid' => $netid,
            'db_error' => $elc_db->error,
        ));
        @unlink($targetFile);
        ar_json_response(500, array(
            'ok' => false,
            'message' => 'Could not bind recording data.',
            'details' => $elc_db->error
        ));
    }
    if (!$query || !$query->execute()) {
        ar_audio_log('upload_db_insert_failed', array(
            'submission_id' => $submissionId,
            'prompt_id' => $promptId,
            'netid' => $netid,
            'db_error' => $elc_db->error,
        ));
        @unlink($targetFile);
        ar_json_response(500, array(
            'ok' => false,
            'message' => 'Could not save recording metadata to the database.',
            'details' => $elc_db->error
        ));
    }

    ar_kick_audio_worker($submissionId);
    ar_audio_log('upload_db_insert_success', array(
        'submission_id' => $submissionId,
        'prompt_id' => $promptId,
        'netid' => $netid,
        'status' => $status,
        'transcription_status' => $transcriptionStatus,
    ));

    $response = array(
        'ok' => true,
        'message' => 'Your response has been saved. Transcription is processing in the background.',
        'submission_id' => $submissionId,
        'status' => $status,
        'transcription_status' => $transcriptionStatus,
        'transcription_text' => $transcriptionText,
        'transcription_error' => $transcriptionError,
        'transcription_source' => $transcriptionSource,
        'transcription_required' => $promptTranscriptionRequired
    );
    ar_json_response(200, $response);
} else {
    ar_json_response(500, array(
        'ok' => false,
        'message' => 'There was an error saving the uploaded file. Please refresh and try again.'
    ));
}
