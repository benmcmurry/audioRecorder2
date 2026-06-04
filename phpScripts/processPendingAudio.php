<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(405);
    echo "This endpoint is intended for CLI use only.";
    exit;
}

include_once(dirname(__FILE__) . '/../../../connectFiles/connect_ar.php');
include_once(__DIR__ . '/audioProcessingCommon.php');

set_error_handler(function ($severity, $message, $file, $line) {
    ar_audio_log('worker_php_error', array(
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ));
    return false;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    ar_audio_log('worker_shutdown_fatal', array(
        'type' => $error['type'],
        'message' => isset($error['message']) ? $error['message'] : null,
        'file' => isset($error['file']) ? $error['file'] : null,
        'line' => isset($error['line']) ? $error['line'] : null,
    ));
});

function ar_claim_audio_job($db, $rowId)
{
    $query = $db->prepare("UPDATE Audio_files SET status = 'transcribing', transcription_status = 'processing', transcription_error = NULL, processing_started_at = NOW() WHERE id = ? AND transcription_status IN ('pending', 'failed') AND (status IN ('uploaded', 'failed_transcription') OR (status = 'transcribing' AND (processing_started_at IS NULL OR processing_started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE))))");
    if (!$query) {
        ar_audio_log('worker_claim_prepare_failed', array(
            'row_id' => $rowId,
            'db_error' => $db->error,
        ));
        return false;
    }
    $query->bind_param("i", $rowId);
    if (!$query->execute()) {
        ar_audio_log('worker_claim_execute_failed', array(
            'row_id' => $rowId,
            'db_error' => $query->error,
        ));
        return false;
    }
    return $query->affected_rows === 1;
}

function ar_finish_audio_job($db, $rowId, $status, $transcriptionText, $transcriptionError, $source)
{
    $query = $db->prepare("UPDATE Audio_files SET status = ?, transcription_status = ?, transcription_text = ?, transcription_error = ?, transcription_source = ?, processing_finished_at = NOW() WHERE id = ?");
    if (!$query) {
        ar_audio_log('worker_finish_prepare_failed', array(
            'row_id' => $rowId,
            'db_error' => $db->error,
        ));
        return false;
    }

    $transcriptionStatus = ($status === 'complete') ? 'complete' : 'failed';
    $query->bind_param("sssssi", $status, $transcriptionStatus, $transcriptionText, $transcriptionError, $source, $rowId);
    if (!$query->execute()) {
        ar_audio_log('worker_finish_execute_failed', array(
            'row_id' => $rowId,
            'db_error' => $query->error,
        ));
        return false;
    }

    return true;
}

function ar_load_pending_audio_jobs($db, $limit)
{
    $rows = array();
    $query = $db->prepare("SELECT id, filename, filetype, transcription_text, transcription_error, submission_id FROM Audio_files WHERE status IN ('uploaded', 'failed_transcription', 'transcribing') ORDER BY date_created ASC LIMIT ?");
    if (!$query) {
        ar_audio_log('worker_load_prepare_failed', array(
            'db_error' => $db->error,
        ));
        return $rows;
    }
    $query->bind_param("i", $limit);
    if (!$query->execute()) {
        ar_audio_log('worker_load_execute_failed', array(
            'db_error' => $query->error,
        ));
        return $rows;
    }
    $result = $query->get_result();
    if (!$result) {
        ar_audio_log('worker_load_get_result_failed', array(
            'db_error' => $query->error,
        ));
        return $rows;
    }
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    return $rows;
}

function ar_extract_audio_path($filename)
{
    $appRoot = realpath(dirname(__FILE__) . '/..');
    $candidate = realpath($appRoot . '/' . ltrim((string) $filename, '/'));
    if ($candidate && strpos($candidate, $appRoot . DIRECTORY_SEPARATOR) === 0 && is_file($candidate)) {
        return $candidate;
    }

    return false;
}

$processed = 0;
$targetSubmissionId = isset($argv[1]) ? trim((string) $argv[1]) : '';
$context = array(
    'submission_id' => $targetSubmissionId,
    'pid' => function_exists('getmypid') ? getmypid() : null,
);
ar_audio_log('worker_start', $context);
$jobs = array();
if ($targetSubmissionId !== '') {
    $query = $elc_db->prepare("SELECT id, filename, filetype, transcription_text, transcription_error, submission_id FROM Audio_files WHERE submission_id = ? LIMIT 1");
    if (!$query) {
        ar_audio_log('worker_target_prepare_failed', array(
            'submission_id' => $targetSubmissionId,
            'db_error' => $elc_db->error,
        ));
    } else {
        $query->bind_param("s", $targetSubmissionId);
        if (!$query->execute()) {
            ar_audio_log('worker_target_execute_failed', array(
                'submission_id' => $targetSubmissionId,
                'db_error' => $query->error,
            ));
        } else {
            $result = $query->get_result();
            if (!$result) {
                ar_audio_log('worker_target_get_result_failed', array(
                    'submission_id' => $targetSubmissionId,
                    'db_error' => $query->error,
                ));
            } elseif ($row = $result->fetch_assoc()) {
                $jobs[] = $row;
            }
        }
    }
}
if (!$jobs) {
    $jobs = ar_load_pending_audio_jobs($elc_db, 25);
}
ar_audio_log('worker_jobs_loaded', array(
    'submission_id' => $targetSubmissionId,
    'job_count' => count($jobs),
));
foreach ($jobs as $job) {
    ar_audio_log('worker_job_consider', array(
        'submission_id' => isset($job['submission_id']) ? $job['submission_id'] : null,
        'row_id' => (int) $job['id'],
        'filename' => isset($job['filename']) ? $job['filename'] : null,
    ));
    if (!ar_claim_audio_job($elc_db, (int) $job['id'])) {
        ar_audio_log('worker_job_skipped_claim', array(
            'submission_id' => isset($job['submission_id']) ? $job['submission_id'] : null,
            'row_id' => (int) $job['id'],
        ));
        continue;
    }
    ar_audio_log('worker_job_claimed', array(
        'submission_id' => isset($job['submission_id']) ? $job['submission_id'] : null,
        'row_id' => (int) $job['id'],
    ));

    $audioPath = ar_extract_audio_path($job['filename']);
    if (!$audioPath) {
        ar_audio_log('worker_audio_missing', array(
            'submission_id' => isset($job['submission_id']) ? $job['submission_id'] : null,
            'row_id' => (int) $job['id'],
            'filename' => isset($job['filename']) ? $job['filename'] : null,
        ));
        ar_finish_audio_job($elc_db, (int) $job['id'], 'failed_transcription', '', 'Audio file missing on disk.', 'queue');
        continue;
    }

    ar_audio_log('worker_transcribe_start', array(
        'submission_id' => isset($job['submission_id']) ? $job['submission_id'] : null,
        'row_id' => (int) $job['id'],
        'audio_path' => $audioPath,
    ));
    list($ok, $text, $sourceOrError) = ar_try_transcribe_recording($audioPath);
    if ($ok) {
        ar_finish_audio_job($elc_db, (int) $job['id'], 'complete', $text, '', $sourceOrError);
        ar_audio_log('worker_transcribe_success', array(
            'submission_id' => isset($job['submission_id']) ? $job['submission_id'] : null,
            'row_id' => (int) $job['id'],
            'source' => $sourceOrError,
            'text_length' => strlen($text),
        ));
    } else {
        ar_finish_audio_job($elc_db, (int) $job['id'], 'failed_transcription', '', $sourceOrError, 'queue');
        ar_audio_log('worker_transcribe_failed', array(
            'submission_id' => isset($job['submission_id']) ? $job['submission_id'] : null,
            'row_id' => (int) $job['id'],
            'error' => $sourceOrError,
        ));
    }

    $processed++;
}

ar_audio_log('worker_done', array(
    'submission_id' => $targetSubmissionId,
    'processed' => $processed,
));
echo "Processed {$processed} job(s)." . PHP_EOL;
