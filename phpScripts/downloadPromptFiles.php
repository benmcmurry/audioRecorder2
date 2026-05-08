<?php
include_once('../cas-go.php');
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');

function ar_zip_error($statusCode, $message)
{
    http_response_code($statusCode);
    echo $message;
    exit;
}

function ar_safe_zip_name($value, $fallback)
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $value);
    $value = trim($value, '_');
    return $value === '' ? $fallback : $value;
}

function ar_unique_zip_entry($zip, $name)
{
    if ($zip->locateName($name) === false) {
        return $name;
    }

    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $base = $extension ? substr($name, 0, -strlen($extension) - 1) : $name;
    for ($i = 2; $i < 1000; $i++) {
        $candidate = $base . '_' . $i . ($extension ? '.' . $extension : '');
        if ($zip->locateName($candidate) === false) {
            return $candidate;
        }
    }

    return $base . '_' . uniqid() . ($extension ? '.' . $extension : '');
}

if (!class_exists('ZipArchive')) {
    ar_zip_error(500, 'Zip downloads are not available on this server.');
}

if (!isset($_GET['prompt_id']) || !isset($_GET['type'])) {
    ar_zip_error(400, 'Missing download parameters.');
}

$promptId = $_GET['prompt_id'];
$type = $_GET['type'];
if ($promptId === '' || ($type !== 'audio' && $type !== 'transcripts')) {
    ar_zip_error(400, 'Invalid download parameters.');
}

$promptQuery = $elc_db->prepare("SELECT prompt_id FROM Prompts WHERE prompt_id = ? AND netid = ? LIMIT 1");
$promptQuery->bind_param("ss", $promptId, $netid);
$promptQuery->execute();
$promptResult = $promptQuery->get_result();
if (!$promptResult || !$promptResult->fetch_assoc()) {
    ar_zip_error(403, 'You do not have access to this prompt.');
}

$query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid WHERE Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC, Audio_files.date_created DESC");
$query->bind_param("s", $promptId);
$query->execute();
$result = $query->get_result();

$tempFile = tempnam(sys_get_temp_dir(), 'ar_zip_');
if (!$tempFile) {
    ar_zip_error(500, 'Could not create the zip file.');
}

$zip = new ZipArchive();
if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
    @unlink($tempFile);
    ar_zip_error(500, 'Could not open the zip file.');
}

$appRoot = realpath(__DIR__ . '/..');
$added = 0;
$allTranscriptions = '';
while ($row = $result->fetch_assoc()) {
    $studentName = $row['user_name'] ? $row['user_name'] : $row['netid'];
    $baseName = 'prompt_' . ar_safe_zip_name($row['prompt_id'], 'prompt') . '_' . ar_safe_zip_name($studentName, 'student') . '_' . ar_safe_zip_name($row['id'], 'response');

    if ($type === 'transcripts') {
        $allTranscriptions .= $studentName . "\n";
        $allTranscriptions .= str_repeat('-', strlen($studentName)) . "\n";
        $allTranscriptions .= (string) $row['transcription_text'] . "\n\n";

        $entryName = ar_unique_zip_entry($zip, $baseName . '_transcript.txt');
        $zip->addFromString($entryName, (string) $row['transcription_text']);
        $added++;
        continue;
    }

    $audioPath = realpath($appRoot . '/' . $row['filename']);
    if (!$audioPath || strpos($audioPath, $appRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($audioPath)) {
        continue;
    }

    $extension = pathinfo($audioPath, PATHINFO_EXTENSION);
    $entryName = ar_unique_zip_entry($zip, $baseName . ($extension ? '.' . $extension : ''));
    if ($zip->addFile($audioPath, $entryName)) {
        $added++;
    }
}

if ($type === 'transcripts' && $allTranscriptions !== '') {
    $zip->addFromString('all_transcriptions.txt', $allTranscriptions);
    $added++;
}

if ($added === 0) {
    $zip->addFromString('no_files_found.txt', 'No files were available for this download.');
}

$zip->close();

$zipName = 'prompt_' . ar_safe_zip_name($promptId, 'prompt') . '_' . ($type === 'audio' ? 'audio' : 'transcripts') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tempFile));
readfile($tempFile);
@unlink($tempFile);
exit;
