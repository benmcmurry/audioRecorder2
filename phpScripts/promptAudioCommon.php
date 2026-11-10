<?php
include_once(__DIR__ . '/openAiApiCommon.php');
include_once(__DIR__ . '/openAiActivityCommon.php');

function composePromptSpeechText($promptText, $prepareTime, $responseTime)
{
    return trim((string) $promptText) .
        " You have " . (string) $prepareTime .
        " seconds to prepare and " . (string) $responseTime .
        " seconds to respond.";
}

function promptAudioDir()
{
    return __DIR__ . "/../generatedAudio/prompts";
}

function promptAudioPaths($promptId)
{
    $safePromptId = preg_replace("/[^0-9]/", "", (string) $promptId);
    if ($safePromptId === "") {
        $safePromptId = "0";
    }

    $baseName = "prompt_" . $safePromptId;
    $dir = promptAudioDir();
    return array(
        "dir" => $dir,
        "audio" => $dir . "/" . $baseName . ".mp3",
        "hash" => $dir . "/" . $baseName . ".sha256"
    );
}

function ensurePromptAudioDirectory()
{
    $dir = promptAudioDir();
    if (!is_dir($dir)) {
        return @mkdir($dir, 0775, true);
    }
    return true;
}

function requestOpenAiPromptAudio($speechText, $db, $netid, $promptId)
{
    $model = 'gpt-4o-mini-tts-2025-12-15';
    $activityId = ar_openai_activity_begin($db, 'speech_generation', $netid, $model, 'prompt', $promptId);
    if ($activityId === false) {
        return array(false, 'OpenAI activity logging is unavailable; the request was not sent.');
    }
    $startedAt = microtime(true);

    if (!function_exists("curl_init")) {
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'curl_unavailable', 'client_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, "PHP cURL extension is required.");
    }

    $apiKey = ar_get_openai_api_key();
    if (!$apiKey) {
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'missing_api_key', 'configuration_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, "OPENAI_API_KEY is not configured on the server.");
    }

    $requestBody = array(
        "model" => $model,
        "voice" => "cedar",
        "input" => $speechText,
        "response_format" => "mp3",
        "instructions" => "Voice Affect: Warm and composed. Tone: Clear and friendly. Pacing: Steady and moderate."
    );

    $ch = curl_init("https://api.openai.com/v1/audio/speech");
    if ($ch === false) {
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'curl_init_failed', 'transport_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, "OpenAI speech request failed.");
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ));
    $encodedBody = json_encode($requestBody);
    if ($encodedBody === false) {
        curl_close($ch);
        ar_openai_activity_finish($db, $activityId, 'failed', null, 'request_encoding_failed', 'client_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, 'OpenAI speech request could not be prepared.');
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBody);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlNumber = curl_errno($ch);
    curl_close($ch);

    if ($apiResponse === false || $curlNumber !== 0) {
        $errorCode = $curlNumber > 0 ? 'curl_' . $curlNumber : 'transport_error';
        ar_openai_activity_finish($db, $activityId, 'failed', null, $errorCode, 'transport_error', ar_openai_activity_duration_ms($startedAt));
        return array(false, "OpenAI speech request failed.");
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $details = ar_openai_activity_error_details($apiResponse);
        $safeDetails = array();
        foreach (array('code', 'type') as $field) {
            if (!empty($details[$field])) {
                $safeDetails[] = $details[$field];
            }
        }
        ar_openai_activity_finish($db, $activityId, 'failed', (int) $httpCode, $details['code'], $details['type'], ar_openai_activity_duration_ms($startedAt));
        $detailText = $safeDetails ? ' (' . implode('/', $safeDetails) . ')' : '';
        return array(false, "OpenAI speech request failed with HTTP status " . (int) $httpCode . $detailText . ".");
    }

    ar_openai_activity_finish($db, $activityId, 'succeeded', (int) $httpCode, null, null, ar_openai_activity_duration_ms($startedAt));
    return array(true, $apiResponse);
}

function ensurePromptAudioCached($promptId, $speechText, $db, $netid)
{
    if (!ensurePromptAudioDirectory()) {
        return array(false, "Could not create prompt audio directory.");
    }

    $paths = promptAudioPaths($promptId);
    $speechHash = hash("sha256", $speechText);

    if (is_file($paths["audio"]) && is_file($paths["hash"])) {
        $existingHash = trim((string) @file_get_contents($paths["hash"]));
        if ($existingHash === $speechHash) {
            return array(true, "unchanged");
        }
    }

    list($ok, $audioOrError) = requestOpenAiPromptAudio($speechText, $db, $netid, $promptId);
    if (!$ok) {
        return array(false, $audioOrError);
    }

    $tmpAudioPath = $paths["audio"] . ".tmp";
    if (@file_put_contents($tmpAudioPath, $audioOrError) === false) {
        return array(false, "Could not write temporary prompt audio file.");
    }
    if (!@rename($tmpAudioPath, $paths["audio"])) {
        @unlink($tmpAudioPath);
        return array(false, "Could not finalize prompt audio file.");
    }

    if (@file_put_contents($paths["hash"], $speechHash) === false) {
        return array(false, "Could not write prompt audio hash file.");
    }

    return array(true, "generated");
}

?>
