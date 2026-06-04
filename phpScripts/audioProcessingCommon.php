<?php

function ar_get_openai_api_key()
{
    $apiKey = getenv("OPENAI_API_KEY");
    if (!$apiKey && isset($_SERVER["OPENAI_API_KEY"])) {
        $apiKey = $_SERVER["OPENAI_API_KEY"];
    }
    if (!$apiKey && isset($_ENV["OPENAI_API_KEY"])) {
        $apiKey = $_ENV["OPENAI_API_KEY"];
    }
    return $apiKey;
}

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

function ar_worker_environment_prefix()
{
    $env = array();

    $apiKey = ar_get_openai_api_key();
    if ($apiKey) {
        $env[] = 'OPENAI_API_KEY=' . escapeshellarg($apiKey);
    }

    $ffmpeg = getenv('FFMPEG_BINARY');
    if (!$ffmpeg && PHP_OS_FAMILY === 'Darwin') {
        $ffmpeg = '/opt/homebrew/bin/ffmpeg';
    } elseif (!$ffmpeg) {
        $ffmpeg = '/usr/bin/ffmpeg';
    }
    if ($ffmpeg) {
        $env[] = 'FFMPEG_BINARY=' . escapeshellarg($ffmpeg);
    }

    return $env ? implode(' ', $env) . ' ' : '';
}

function ar_transcribe_with_openai($audioPath)
{
    if (!function_exists('curl_init')) {
        return array(false, 'PHP cURL extension is required for transcription.');
    }

    $apiKey = ar_get_openai_api_key();
    if (!$apiKey) {
        return array(false, 'OPENAI_API_KEY is not configured on the server.');
    }

    if (!is_file($audioPath)) {
        return array(false, 'Audio file not found for transcription.');
    }

    $mimeType = function_exists('mime_content_type') ? mime_content_type($audioPath) : 'application/octet-stream';
    if (!$mimeType || strpos($mimeType, 'audio/') !== 0) {
        $mimeType = 'application/octet-stream';
    }

    $postFields = array(
        'model' => 'whisper-1',
        'response_format' => 'json',
        'file' => new CURLFile($audioPath, $mimeType, basename($audioPath))
    );

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apiKey
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($apiResponse === false || $curlError) {
        return array(false, 'OpenAI transcription request failed: ' . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return array(false, 'OpenAI transcription error: ' . $apiResponse);
    }

    $decoded = json_decode($apiResponse, true);
    if (!is_array($decoded) || !isset($decoded['text'])) {
        return array(false, 'OpenAI transcription response did not include text.');
    }

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

function ar_try_transcribe_recording($audioPath)
{
    list($ok, $value) = ar_transcribe_with_openai($audioPath);
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

    list($retryOk, $retryValue) = ar_transcribe_with_openai($fallbackMp3);
    @unlink($fallbackMp3);

    if ($retryOk) {
      return array(true, $retryValue, 'openai');
    }

    return array(false, null, $retryValue);
}
