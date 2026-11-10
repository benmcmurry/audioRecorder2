<?php
include_once(__DIR__ . '/openAiApiCommon.php');
include_once(__DIR__ . '/openAiActivityCommon.php');

function ar_ffmpeg_binary()
{
    $envBinary = getenv("FFMPEG_BINARY");
    if ($envBinary) {
        return $envBinary;
    }

    if (PHP_OS_FAMILY === 'Darwin') {
        return '/opt/homebrew/bin/ffmpeg';
    }

    return '/usr/bin/ffmpeg';
}

function ar_php_cli_binary()
{
    if (defined('PHP_BINARY') && PHP_BINARY && is_string(PHP_BINARY)) {
        return PHP_BINARY;
    }

    if (defined('PHP_BINDIR') && PHP_BINDIR) {
        $candidate = rtrim(PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php';
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    $command = @shell_exec('command -v php 2>/dev/null');
    $command = is_string($command) ? trim($command) : '';
    if ($command !== '') {
        return $command;
    }

    return 'php';
}

function ar_worker_environment()
{
    $env = PHP_VERSION_ID >= 70100 ? getenv() : array();
    if (!is_array($env)) {
        $env = array();
        foreach (array($_ENV, $_SERVER) as $source) {
            foreach ($source as $name => $value) {
                if (is_string($name) && preg_match('/\\A[A-Za-z_][A-Za-z0-9_]*\\z/', $name) && is_scalar($value)) {
                    $env[$name] = (string) $value;
                }
            }
        }
    }

    $apiKey = ar_get_openai_api_key();
    if ($apiKey) {
        $env['OPENAI_API_KEY'] = $apiKey;
    }

    $ffmpeg = getenv('FFMPEG_BINARY');
    if (!$ffmpeg && PHP_OS_FAMILY === 'Darwin') {
        $ffmpeg = '/opt/homebrew/bin/ffmpeg';
    } elseif (!$ffmpeg) {
        $ffmpeg = '/usr/bin/ffmpeg';
    }
    if ($ffmpeg) {
        $env['FFMPEG_BINARY'] = $ffmpeg;
    }

    return $env;
}

function ar_transcribe_with_openai($audioPath, $db, $netid, $recordingId)
{
    $model = 'whisper-1';
    $activityId = ar_openai_activity_begin($db, 'transcription', $netid, $model, 'recording', $recordingId);
    if ($activityId === false) {
        return array(false, 'OpenAI activity logging is unavailable; the request was not sent.');
    }
    $startedAt = microtime(true);

    if (!function_exists('curl_init')) {
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'curl_unavailable', 'client_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, 'PHP cURL extension is required for transcription.');
    }

    $apiKey = ar_get_openai_api_key();
    if (!$apiKey) {
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'missing_api_key', 'configuration_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, 'OPENAI_API_KEY is not configured on the server.');
    }

    if (!is_file($audioPath)) {
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'audio_file_missing', 'client_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, 'Audio file not found for transcription.');
    }

    $mimeType = function_exists('mime_content_type') ? mime_content_type($audioPath) : 'application/octet-stream';
    if (!$mimeType || strpos($mimeType, 'audio/') !== 0) {
        $mimeType = 'application/octet-stream';
    }

    $postFields = array(
        'model' => $model,
        'response_format' => 'json',
        'file' => new CURLFile($audioPath, $mimeType, basename($audioPath))
    );

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    if ($ch === false) {
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'curl_init_failed', 'transport_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, 'OpenAI transcription request failed.');
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apiKey
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlNumber = curl_errno($ch);
    curl_close($ch);

    if ($apiResponse === false || $curlNumber !== 0) {
        $errorCode = $curlNumber > 0 ? 'curl_' . $curlNumber : 'transport_error';
        ar_openai_activity_finish($db, $activityId, 'failed', null, $errorCode, 'transport_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, 'OpenAI transcription request failed.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $details = ar_openai_activity_error_details($apiResponse);
        ar_openai_activity_finish($db, $activityId, 'failed', (int) $httpCode, $details['code'], $details['type'], ar_openai_activity_duration_ms($startedAt));
        return array(false, 'OpenAI transcription request failed with HTTP status ' . (int) $httpCode . '.');
    }

    $decoded = json_decode($apiResponse, true);
    if (!is_array($decoded) || !isset($decoded['text'])) {
        ar_openai_activity_finish($db, $activityId, 'failed', (int) $httpCode, 'invalid_response', 'client_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, 'OpenAI transcription response did not include text.');
    }

    ar_openai_activity_finish($db, $activityId, 'succeeded', (int) $httpCode, null, null, ar_openai_activity_duration_ms($startedAt));
    return array(true, trim((string) $decoded['text']));
}

function ar_json_response($statusCode, $payload)
{
    if (ob_get_length()) {
        @ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function ar_audio_log_path()
{
    $appRoot = realpath(dirname(__FILE__) . '/..');
    $logDir = $appRoot . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logPath = $logDir . DIRECTORY_SEPARATOR . 'audio_recorder_pipeline.log';
    if (!is_dir($logDir) || (file_exists($logDir) && !is_writable($logDir))) {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audio_recorder_pipeline.log';
    }

    if (file_exists($logPath) && !is_writable($logPath)) {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audio_recorder_pipeline.log';
    }

    return $logPath;
}

function ar_audio_log($event, array $context = array())
{
    $entry = array(
        'timestamp' => date('c'),
        'event' => $event,
        'context' => $context,
    );

    $line = json_encode($entry) . PHP_EOL;
    $path = ar_audio_log_path();
    $ok = @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    if ($ok === false) {
        @error_log('[audioRecorder] ' . trim($line));
    }
}

function ar_audio_status_is_processable($status)
{
    return in_array($status, array('uploaded', 'failed_transcription'), true);
}

function ar_try_transcribe_recording($audioPath, $db, $netid, $recordingId)
{
    list($ok, $value) = ar_transcribe_with_openai($audioPath, $db, $netid, $recordingId);
    if ($ok) {
        return array(true, $value, 'openai');
    }

    $fallbackPath = tempnam(sys_get_temp_dir(), 'ar_aud_');
    if ($fallbackPath === false) {
        return array(false, null, $value);
    }

    $fallbackMp3 = $fallbackPath . '.mp3';
    @unlink($fallbackPath);

    $ffmpeg = ar_ffmpeg_binary();
    $command = escapeshellcmd($ffmpeg) . ' -y -i ' . escapeshellarg($audioPath) . ' ' . escapeshellarg($fallbackMp3) . ' 2>&1';
    $output = array();
    $exitCode = 0;
    @exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !is_file($fallbackMp3) || filesize($fallbackMp3) === 0) {
        @unlink($fallbackMp3);
        return array(false, null, $value . ' Fallback conversion failed.');
    }

    list($retryOk, $retryValue) = ar_transcribe_with_openai($fallbackMp3, $db, $netid, $recordingId);
    @unlink($fallbackMp3);

    if ($retryOk) {
      return array(true, $retryValue, 'openai');
    }

    return array(false, null, $retryValue);
}
