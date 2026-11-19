<?php
include_once(__DIR__ . '/cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 2) . '/private-config') . '/connectFiles/connect_ar.php');
include_once(__DIR__ . '/phpScripts/audioProcessingCommon.php');
require_once(__DIR__ . '/lti/ags.php');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

define('SITE_ROOT', realpath(dirname(__FILE__)));

function ar_delete_replaced_audio_file($relativePath)
{
    $appRoot = realpath(SITE_ROOT);
    if (!$appRoot || !is_string($relativePath) || trim($relativePath) === '') {
        return;
    }
    $audioPath = realpath($appRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));
    if (!$audioPath || strpos($audioPath, $appRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($audioPath)) {
        return;
    }
    @unlink($audioPath);
}

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

$targetdir = '/uploads/';
$submissionId = isset($_POST['submission_id']) ? (string) $_POST['submission_id'] : '';
$promptId = isset($_POST['prompt_id']) ? (string) $_POST['prompt_id'] : '';
$activityId = isset($_POST['activity_id']) ? trim((string) $_POST['activity_id']) : '';
$activityItemId = isset($_POST['activity_item_id']) ? (int) $_POST['activity_item_id'] : 0;
$activityReplacementRequested = isset($_POST['activity_replace']) && (string) $_POST['activity_replace'] === '1';
$replaceActivityResponse = false;
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

if (!ar_lti_validate_upload_context($promptId, $activityId, (string) $activityItemId, $netid)) {
    ar_json_response(403, array('ok' => false, 'message' => 'This recording does not match the active Canvas assignment.'));
}

$activityItemId = max(0, $activityItemId);
if ($activityId !== '' || $activityItemId > 0) {
    if (!preg_match('/\A[a-f0-9]{32}\z/i', $activityId) || $activityItemId <= 0) {
        ar_json_response(400, array('ok' => false, 'message' => 'The activity step is invalid.'));
    }
    $activityItemQuery = $elc_db->prepare("SELECT Activity_items.item_id, Activities.netid AS activity_owner, Prompts.netid AS prompt_owner FROM Activities JOIN Activity_items ON Activity_items.activity_id = Activities.activity_id JOIN Prompts ON Prompts.prompt_id = Activity_items.prompt_id WHERE Activities.activity_id = ? AND Activities.archive = 0 AND Activity_items.item_id = ? AND Activity_items.prompt_id = ? LIMIT 1");
    if (!$activityItemQuery) {
        ar_json_response(500, array('ok' => false, 'message' => 'Could not validate the activity step.'));
    }
    $activityItemQuery->bind_param('sis', $activityId, $activityItemId, $promptId);
    $activityItemQuery->execute();
    $activityItemResult = $activityItemQuery->get_result();
    $activityItem = $activityItemResult ? $activityItemResult->fetch_assoc() : null;
    if (!$activityItem) {
        ar_json_response(404, array('ok' => false, 'message' => 'This activity step is unavailable.'));
    }
    $creatorOwnsActivityAndPrompt = (string) $activityItem['activity_owner'] === $netid
        && (string) $activityItem['prompt_owner'] === $netid;
    if ($activityReplacementRequested && !$creatorOwnsActivityAndPrompt) {
        ar_json_response(403, array('ok' => false, 'message' => 'Only the creator of this activity and its prompt can replace an earlier response.'));
    }
    $replaceActivityResponse = $activityReplacementRequested && $creatorOwnsActivityAndPrompt;
} else {
    if ($activityReplacementRequested) {
        ar_json_response(403, array('ok' => false, 'message' => 'An activity is required to replace an earlier response.'));
    }
    $activityPromptQuery = $elc_db->prepare("SELECT item_id FROM Activity_items WHERE prompt_id = ? LIMIT 1");
    if ($activityPromptQuery) {
        $activityPromptQuery->bind_param('s', $promptId);
        $activityPromptQuery->execute();
        $activityPromptResult = $activityPromptQuery->get_result();
        if ($activityPromptResult && $activityPromptResult->fetch_assoc()) {
            ar_json_response(403, array('ok' => false, 'message' => 'This prompt must be answered through its activity link.'));
        }
    }
}

$promptQuery = $elc_db->prepare("SELECT prompt_id, netid, transcription, archive FROM Prompts WHERE prompt_id = ? LIMIT 1");
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
if ($replaceActivityResponse && (string) $promptRow['netid'] !== $netid) {
    ar_json_response(403, array('ok' => false, 'message' => 'Only the prompt creator can replace this response.'));
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
        ar_lti_publish_completed_submission($activityId !== '' ? 'activity' : 'prompt', $activityId !== '' ? $activityId : $promptId, $netid);
        ar_json_response(200, array(
            'ok' => true,
            'message' => 'Your response was already saved.',
            'response_id' => (int) $existingRow['id'],
            'submission_id' => $submissionId,
            'status' => isset($existingRow['status']) ? $existingRow['status'] : 'uploaded',
            'transcription_status' => isset($existingRow['transcription_status']) ? $existingRow['transcription_status'] : 'pending',
            'transcription_text' => isset($existingRow['transcription_text']) ? $existingRow['transcription_text'] : '',
            'transcription_error' => isset($existingRow['transcription_error']) ? $existingRow['transcription_error'] : '',
            'transcription_source' => isset($existingRow['transcription_source']) ? $existingRow['transcription_source'] : 'queue'
        ));
    }
}

$priorResponses = array();
if (!$replaceActivityResponse) {
    $priorResponseQuery = $elc_db->prepare("SELECT id FROM Audio_files WHERE prompt_id = ? AND netid = ? ORDER BY id DESC LIMIT 1");
    if (!$priorResponseQuery) {
        ar_json_response(500, array('ok' => false, 'message' => 'Could not check for a previous response.'));
    }
    $priorResponseQuery->bind_param('ss', $promptId, $netid);
    $priorResponseQuery->execute();
    $priorResponseResult = $priorResponseQuery->get_result();
    if (!$priorResponseResult) {
        ar_json_response(500, array('ok' => false, 'message' => 'Could not check for a previous response.'));
    }
    if ($priorResponseResult->fetch_assoc()) {
        ar_json_response(409, array('ok' => false, 'message' => 'A response has already been saved for this prompt.'));
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
    $newResponseId = 0;
    try {
        $elc_db->begin_transaction();
        if (!$query->execute()) {
            throw new RuntimeException('Could not save recording metadata to the database.');
        }
        $newResponseId = (int) $query->insert_id;

        if ($replaceActivityResponse) {
            $oldResponsesQuery = $elc_db->prepare('SELECT id, filename FROM Audio_files WHERE prompt_id = ? AND netid = ? AND id <> ? FOR UPDATE');
            if (!$oldResponsesQuery) {
                throw new RuntimeException('Could not find the previous activity response.');
            }
            $oldResponsesQuery->bind_param('ssi', $promptId, $netid, $newResponseId);
            if (!$oldResponsesQuery->execute()) {
                throw new RuntimeException('Could not find the previous activity response.');
            }
            $oldResponsesResult = $oldResponsesQuery->get_result();
            if (!$oldResponsesResult) {
                throw new RuntimeException('Could not read the previous activity responses.');
            }
            while ($oldResponsesResult && $oldResponse = $oldResponsesResult->fetch_assoc()) {
                $priorResponses[] = $oldResponse;
            }

            $deletePriorResponses = $elc_db->prepare('DELETE FROM Audio_files WHERE prompt_id = ? AND netid = ? AND id <> ?');
            if (!$deletePriorResponses) {
                throw new RuntimeException('Could not replace the previous activity response.');
            }
            $deletePriorResponses->bind_param('ssi', $promptId, $netid, $newResponseId);
            if (!$deletePriorResponses->execute()) {
                throw new RuntimeException('Could not replace the previous activity response.');
            }
        }

        if (!$elc_db->commit()) {
            throw new RuntimeException('Could not commit the saved response.');
        }
    } catch (Throwable $error) {
        $elc_db->rollback();
        ar_audio_log('upload_db_insert_failed', array(
            'submission_id' => $submissionId,
            'prompt_id' => $promptId,
            'netid' => $netid,
            'db_error' => $error->getMessage(),
        ));
        @unlink($targetFile);
        ar_json_response(500, array(
            'ok' => false,
            'message' => 'Could not save recording metadata to the database.',
        ));
    }

    foreach ($priorResponses as $priorResponse) {
        ar_delete_replaced_audio_file(isset($priorResponse['filename']) ? (string) $priorResponse['filename'] : '');
    }

    ar_kick_audio_worker($submissionId);
    ar_lti_publish_completed_submission($activityId !== '' ? 'activity' : 'prompt', $activityId !== '' ? $activityId : $promptId, $netid);
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
        'response_id' => $newResponseId,
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
