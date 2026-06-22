<?php
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 2) . '/private-config') . '/connectFiles/connect_ar.php');
include_once(__DIR__ . '/phpScripts/audioProcessingCommon.php');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

define('SITE_ROOT', realpath(dirname(__FILE__)));

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
    if (php_sapi_name() === 'cli' || !function_exists('shell_exec')) {
        ar_audio_log('worker_kick_skipped', array(
            'submission_id' => $submissionId,
            'reason' => php_sapi_name() === 'cli' ? 'cli_context' : 'shell_exec_unavailable',
        ));
        return;
    }

    $worker = escapeshellarg(__DIR__ . '/phpScripts/processPendingAudio.php');
    $phpBinary = escapeshellarg(ar_php_cli_binary());
    $submissionArg = escapeshellarg($submissionId);
    $command = ar_worker_environment_prefix() . $phpBinary . ' ' . $worker . ' ' . $submissionArg . ' > /dev/null 2>&1 &';
    ar_audio_log('worker_kick_attempt', array(
        'submission_id' => $submissionId,
        'php_binary' => ar_php_cli_binary(),
        'worker_script' => __DIR__ . '/phpScripts/processPendingAudio.php',
    ));
    $output = @shell_exec($command);
    ar_audio_log('worker_kick_result', array(
        'submission_id' => $submissionId,
        'shell_output' => $output === null ? null : trim((string) $output),
    ));
}

$targetdir = '/uploads/';
$fileName = isset($_POST['name']) ? (string) $_POST['name'] : '';
$submissionId = isset($_POST['submission_id']) ? (string) $_POST['submission_id'] : '';
$promptId = isset($_POST['prompt_id']) ? (string) $_POST['prompt_id'] : '';
$netid = isset($_POST['netid']) ? (string) $_POST['netid'] : '';
$extension = isset($_POST['extension']) ? (string) $_POST['extension'] : '';
$uploadsPath = SITE_ROOT . $targetdir;

if ($fileName === '' || $submissionId === '' || $promptId === '' || $netid === '' || $extension === '') {
    ar_json_response(400, array(
        'ok' => false,
        'message' => 'Missing required upload fields.'
    ));
}

if (!is_dir($uploadsPath)) {
    mkdir($uploadsPath, 0755, true);
}

if (!is_dir($uploadsPath) || !is_writable($uploadsPath)) {
    http_response_code(500);
    echo "Upload directory is missing or not writable.";
    exit;
}

$targetFile = $uploadsPath . $fileName . $extension;
$fileLocation = "uploads/" . $fileName . $extension;
$storedFileType = isset($_FILES['myBlob']['type']) ? (string) $_FILES['myBlob']['type'] : 'application/octet-stream';

$existingQuery = $elc_db->prepare("SELECT id, prompt_id, netid, filename, filesize, filetype, transcription_text, status, transcription_status, transcription_error, transcription_source, submission_id FROM Audio_files WHERE submission_id = ? LIMIT 1");
if ($existingQuery) {
    $existingQuery->bind_param("s", $submissionId);
    $existingQuery->execute();
    $existingResult = $existingQuery->get_result();
    $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
    if ($existingRow) {
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

$promptTranscriptionRequired = 0;
$promptQuery = $elc_db->prepare("SELECT transcription FROM Prompts WHERE prompt_id = ? LIMIT 1");
if ($promptQuery) {
    $promptQuery->bind_param("s", $promptId);
    $promptQuery->execute();
    $promptResult = $promptQuery->get_result();
    $promptRow = $promptResult ? $promptResult->fetch_assoc() : null;
    if ($promptRow && isset($promptRow['transcription'])) {
        $promptTranscriptionRequired = (int) $promptRow['transcription'];
    }
}

if (move_uploaded_file($_FILES['myBlob']['tmp_name'], $targetFile)) {
    ar_audio_log('upload_file_moved', array(
        'submission_id' => $submissionId,
        'prompt_id' => $promptId,
        'netid' => $netid,
        'target_file' => $targetFile,
        'file_location' => $fileLocation,
        'filesize' => isset($_FILES['myBlob']['size']) ? (int) $_FILES['myBlob']['size'] : null,
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
    $filesize = isset($_FILES['myBlob']['size']) ? (string) $_FILES['myBlob']['size'] : '0';
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
