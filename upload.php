<?php
include_once('../../connectFiles/connect_ar.php');
if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
    // $ffmpeg = "/usr/local/bin/ffmpeg";
    $ffmpeg = "/opt/homebrew/bin/ffmpeg";
} else {
    $ffmpeg = "/usr/bin/ffmpeg";
}
define('SITE_ROOT', realpath(dirname(__FILE__)));

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

function transcribeWithOpenAi($audioPath)
{
    if (!function_exists('curl_init')) {
        return array(false, 'PHP cURL extension is required for transcription.');
    }

    $apiKey = getOpenAiApiKey();
    if (!$apiKey) {
        return array(false, 'OPENAI_API_KEY is not configured on the server.');
    }

    if (!is_file($audioPath)) {
        return array(false, 'Audio file not found for transcription.');
    }

    $postFields = array(
        'model' => 'whisper-1',
        'response_format' => 'json',
        'file' => new CURLFile($audioPath, 'audio/mpeg', basename($audioPath))
    );

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apiKey
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

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

function json_response($statusCode, $payload)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

$targetdir = '/uploads/';
// name of the directory where the files should be stored
$time = date('Y-m-d-His');
$fileName = $_POST['name'];
$uploadsPath = SITE_ROOT . $targetdir;

if (!is_dir($uploadsPath)) {
    mkdir($uploadsPath, 0755, true);
}

if (!is_dir($uploadsPath) || !is_writable($uploadsPath)) {
    http_response_code(500);
    echo "Upload directory is missing or not writable.";
    exit;
}

$targetFile = $uploadsPath . $_POST['name'] . $_POST['extension'];
// echo $_FILES['myBlob']['tmp_name'];
$mp3File = $uploadsPath . $_POST['name'] . "mp3";
$fileLocation = "uploads/" . $fileName . "mp3";
$fallbackFileLocation = "uploads/" . $fileName . $_POST['extension'];
$storedFileType = $_FILES['myBlob']['type'];

$promptTranscriptionRequired = 0;
$promptQuery = $elc_db->prepare("SELECT transcription FROM Prompts WHERE prompt_id = ? LIMIT 1");
if ($promptQuery) {
    $promptQuery->bind_param("s", $_POST['prompt_id']);
    $promptQuery->execute();
    $promptResult = $promptQuery->get_result();
    $promptRow = $promptResult ? $promptResult->fetch_assoc() : null;
    if ($promptRow && isset($promptRow['transcription'])) {
        $promptTranscriptionRequired = (int) $promptRow['transcription'];
    }
}

$transcriptionText = "";
$transcriptionError = "";
$conversionWarning = "";
$transcriptionSource = "browser";
if (move_uploaded_file($_FILES['myBlob']['tmp_name'], $targetFile)) {
    shell_exec($ffmpeg . " -y -i " . escapeshellarg($targetFile) . " " . escapeshellarg($mp3File) . " 2>&1");

    $transcriptionSourcePath = $targetFile;
    if (is_file($mp3File) && filesize($mp3File) > 0) {
        $transcriptionSourcePath = $mp3File;
        $fileLocation = "uploads/" . $fileName . "mp3";
        $storedFileType = "audio/mpeg";
        if (is_file($targetFile)) {
            @unlink($targetFile);
        }
    } else {
        $fileLocation = $fallbackFileLocation;
        $conversionWarning = "MP3 conversion failed; using original uploaded audio for transcription.";
    }

    list($transcriptionOk, $transcriptionValue) = transcribeWithOpenAi($transcriptionSourcePath);
    if ($transcriptionOk) {
        $transcriptionText = $transcriptionValue;
        $transcriptionSource = "openai";
    } else {
        $transcriptionError = $transcriptionValue;
        $transcriptionSource = "browser";
    }
} else {
    json_response(500, array(
        'ok' => false,
        'message' => 'There was an error saving the uploaded file. Please refresh and try again.'
    ));
}


$query = $elc_db->prepare("Insert into Audio_files (prompt_id, netid, filename, filesize, filetype, transcription_text, date_created) Values (?,?,?,?,?,?,now())");
$query->bind_param("ssssss", $_POST['prompt_id'], $_POST['netid'], $fileLocation, $_FILES['myBlob']['size'], $storedFileType, $transcriptionText);
if (!$query || !$query->execute()) {
    json_response(500, array(
        'ok' => false,
        'message' => 'Could not save recording metadata to the database.',
        'details' => $elc_db->error
    ));
}

json_response(200, array(
    'ok' => true,
    'message' => 'Your response has been saved.',
    'transcription_text' => $transcriptionText,
    'transcription_error' => $transcriptionError,
    'conversion_warning' => $conversionWarning,
    'transcription_source' => $transcriptionSource,
    'transcription_required' => $promptTranscriptionRequired
));
