<?php

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

function getOpenAiApiKey()
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

function requestOpenAiPromptAudio($speechText)
{
    if (!function_exists("curl_init")) {
        return array(false, "PHP cURL extension is required.");
    }

    $apiKey = getOpenAiApiKey();
    if (!$apiKey) {
        return array(false, "OPENAI_API_KEY is not configured on the server.");
    }

    $requestBody = array(
        "model" => "gpt-4o-mini-tts-2025-12-15",
        "voice" => "cedar",
        "input" => $speechText,
        "response_format" => "mp3",
        "instructions" => "Voice Affect: Warm and composed. Tone: Clear and friendly. Pacing: Steady and moderate."
    );

    $ch = curl_init("https://api.openai.com/v1/audio/speech");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($apiResponse === false || $curlError) {
        return array(false, "OpenAI request failed: " . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded = json_decode($apiResponse, true);
        if (is_array($decoded)) {
            return array(false, "OpenAI TTS error: " . json_encode($decoded));
        }
        return array(false, "OpenAI TTS error: " . $apiResponse);
    }

    return array(true, $apiResponse);
}

function ensurePromptAudioCached($promptId, $speechText)
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

    list($ok, $audioOrError) = requestOpenAiPromptAudio($speechText);
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
