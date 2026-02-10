<?php

include_once("../cas-go.php");

header("Cache-Control: no-store, max-age=0");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Method not allowed. Use POST."]);
    exit;
}

$rawInput = file_get_contents("php://input");
$payload = json_decode($rawInput, true);

if (!is_array($payload) || !isset($payload["text"])) {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Missing required field: text"]);
    exit;
}

$text = trim($payload["text"]);
if ($text === "") {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Text cannot be empty."]);
    exit;
}

if (strlen($text) > 4000) {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["error" => "Text is too long. Max 4000 characters."]);
    exit;
}

$apiKey = getenv("OPENAI_API_KEY");
if (!$apiKey && isset($_SERVER["OPENAI_API_KEY"])) {
    $apiKey = $_SERVER["OPENAI_API_KEY"];
}

if (!$apiKey) {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["error" => "OPENAI_API_KEY is not configured on the server."]);
    exit;
}

$requestBody = [
    "model" => "gpt-4o-mini-tts-2025-12-15",
    "voice" => "cedar",
    "input" => $text,
    "response_format" => "mp3",
    "instructions" => "Voice Affect: Warm and composed. Tone: Clear and friendly. Pacing: Steady and moderate."
];

if (!function_exists("curl_init")) {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["error" => "PHP cURL extension is required."]);
    exit;
}

$ch = curl_init("https://api.openai.com/v1/audio/speech");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $apiKey,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($apiResponse === false || $curlError) {
    http_response_code(502);
    header("Content-Type: application/json");
    echo json_encode(["error" => "OpenAI request failed: " . $curlError]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code($httpCode);
    header("Content-Type: application/json");
    echo json_encode(["error" => "OpenAI TTS error", "details" => $apiResponse]);
    exit;
}

header("Content-Type: audio/mpeg");
header("Content-Disposition: inline; filename=prompt.mp3");
echo $apiResponse;
exit;

?>
