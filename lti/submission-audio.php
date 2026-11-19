<?php
require_once __DIR__ . '/common.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    ar_lti_error(405, 'Method not allowed.');
}
$token = (string) ($_GET['token'] ?? '');
$responseId = (int) ($_GET['response_id'] ?? 0);
if (!preg_match('/\A[a-f0-9]{64}\z/', $token) || $responseId <= 0) {
    ar_lti_error(404, 'Recording not found.');
}
$db = ar_lti_db();
$hash = hash('sha256', $token);
$query = $db->prepare('SELECT netid, response_ids FROM Lti_submission_links WHERE token_hash = ? LIMIT 1');
$query->bind_param('s', $hash);
$query->execute();
$link = $query->get_result()->fetch_assoc();
$allowedIds = $link ? json_decode((string) $link['response_ids'], true) : null;
if (!$link || !is_array($allowedIds) || !in_array($responseId, array_map('intval', $allowedIds), true)) {
    ar_lti_error(404, 'Recording not found.');
}
$query = $db->prepare('SELECT filename, filetype FROM Audio_files WHERE id = ? AND netid = ? LIMIT 1');
$query->bind_param('is', $responseId, $link['netid']);
$query->execute();
$row = $query->get_result()->fetch_assoc();
if (!$row) {
    ar_lti_error(404, 'Recording not found.');
}
require_once __DIR__ . '/../phpScripts/responseHelpers.php';
$path = ar_audio_file_path($row['filename']);
if (!$path) {
    ar_lti_error(404, 'Audio file is no longer available.');
}
$types = array('webm' => 'audio/webm', 'mp4' => 'audio/mp4', 'mp3' => 'audio/mpeg');
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $types[$extension] ?? 'application/octet-stream';
$size = filesize($path);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="recording.' . (isset($types[$extension]) ? $extension : 'audio') . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '' && preg_match('/\Abytes=(\d*)-(\d*)\z/', $range, $matches)) {
    $start = $matches[1] === '' ? null : (int) $matches[1];
    $end = $matches[2] === '' ? null : (int) $matches[2];
    if ($start === null) {
        $suffixLength = $end;
        if ($suffixLength <= 0) {
            header('Content-Range: bytes */' . $size);
            ar_lti_error(416, 'Invalid byte range.');
        }
        $start = max(0, $size - $suffixLength);
        $end = $size - 1;
    } else {
        $end = $end === null ? $size - 1 : min($end, $size - 1);
    }
    if ($start < 0 || $start >= $size || $end < $start) {
        header('Content-Range: bytes */' . $size);
        ar_lti_error(416, 'Invalid byte range.');
    }
    $length = $end - $start + 1;
    http_response_code(206);
    header('Accept-Ranges: bytes');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . $length);
    $handle = fopen($path, 'rb');
    if ($handle === false || fseek($handle, $start) !== 0) {
        if (is_resource($handle)) fclose($handle);
        ar_lti_error(500, 'Could not read the recording.');
    }
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($handle);
} else {
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . (int) $size);
    readfile($path);
}
exit;
