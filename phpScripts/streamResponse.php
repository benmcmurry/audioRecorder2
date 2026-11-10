<?php
include_once('../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('responseHelpers.php');

function ar_stream_error($statusCode, $message)
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    exit($message);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    ar_stream_error(405, 'Method not allowed.');
}

$responseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($responseId <= 0) {
    ar_stream_error(400, 'Invalid recording.');
}

$row = ar_response_for_download($elc_db, $responseId);
if (!$row) {
    ar_stream_error(404, 'Recording not found.');
}

if ((string) $row['netid'] !== (string) $netid && (string) $row['prompt_owner'] !== (string) $netid) {
    ar_stream_error(403, 'You do not have access to this recording.');
}

$audioPath = ar_audio_file_path($row['filename']);
if (!$audioPath) {
    ar_stream_error(404, 'Audio file not found.');
}

$extension = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
$filetypeByExtension = array(
    'webm' => 'audio/webm',
    'mp4' => 'audio/mp4',
    'mp3' => 'audio/mpeg',
);
$filetype = isset($filetypeByExtension[$extension]) ? $filetypeByExtension[$extension] : 'application/octet-stream';
$fileSize = filesize($audioPath);
if ($fileSize === false) {
    ar_stream_error(500, 'Could not read the recording.');
}

header('Content-Type: ' . $filetype);
header('Content-Disposition: inline; filename="recording.' . (isset($filetypeByExtension[$extension]) ? $extension : 'audio') . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$rangeHeader = isset($_SERVER['HTTP_RANGE']) ? trim((string) $_SERVER['HTTP_RANGE']) : '';
if ($rangeHeader !== '' && preg_match('/\\Abytes=(\\d*)-(\\d*)\\z/', $rangeHeader, $rangeMatch)) {
    $start = $rangeMatch[1] === '' ? null : (int) $rangeMatch[1];
    $end = $rangeMatch[2] === '' ? null : (int) $rangeMatch[2];
    if ($start === null) {
        $suffixLength = $end;
        if ($suffixLength <= 0) {
            header('Content-Range: bytes */' . $fileSize);
            ar_stream_error(416, 'Invalid byte range.');
        }
        $start = max(0, $fileSize - $suffixLength);
        $end = $fileSize - 1;
    } else {
        $end = $end === null ? $fileSize - 1 : min($end, $fileSize - 1);
    }

    if ($start < 0 || $start >= $fileSize || $end < $start) {
        header('Content-Range: bytes */' . $fileSize);
        ar_stream_error(416, 'Invalid byte range.');
    }

    $length = $end - $start + 1;
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . $length);
    $handle = fopen($audioPath, 'rb');
    if ($handle === false || fseek($handle, $start) !== 0) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        ar_stream_error(500, 'Could not read the recording.');
    }
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($handle);
} else {
    header('Content-Length: ' . $fileSize);
    readfile($audioPath);
}
exit;
?>
