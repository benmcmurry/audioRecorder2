<?php
include_once('../cas-go.php');
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('responseHelpers.php');

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
    ar_download_error(500, 'Zip downloads are not available on this server.');
}

if (!isset($_GET['prompt_id']) || !isset($_GET['type'])) {
    ar_download_error(400, 'Missing download parameters.');
}

$promptId = $_GET['prompt_id'];
$type = $_GET['type'];
if ($promptId === '' || ($type !== 'audio' && $type !== 'transcripts')) {
    ar_download_error(400, 'Invalid download parameters.');
}

$promptRow = ar_prompt_for_owner($elc_db, $promptId, $netid);
if (!$promptRow) {
    ar_download_error(403, 'You do not have access to this prompt.');
}

$responses = ar_prompt_responses($elc_db, $promptId);

$tempFile = tempnam(sys_get_temp_dir(), 'ar_zip_');
if (!$tempFile) {
    ar_download_error(500, 'Could not create the zip file.');
}

$zip = new ZipArchive();
if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
    @unlink($tempFile);
    ar_download_error(500, 'Could not open the zip file.');
}

$added = 0;
$allTranscriptions = '';
foreach ($responses as $row) {
    $studentName = ar_student_name($row);
    $baseName = 'prompt_' . ar_safe_file_name($row['prompt_id'], 'prompt') . '_' . ar_safe_file_name($studentName, 'student') . '_' . ar_safe_file_name($row['id'], 'response');

    if ($type === 'transcripts') {
        $allTranscriptions .= $studentName . "\n";
        $allTranscriptions .= str_repeat('-', strlen($studentName)) . "\n";
        $allTranscriptions .= (string) $row['transcription_text'] . "\n\n";

        $entryName = ar_unique_zip_entry($zip, $baseName . '_transcript.txt');
        $zip->addFromString($entryName, (string) $row['transcription_text']);
        $added++;
        continue;
    }

    $audioPath = ar_audio_file_path($row['filename']);
    if (!$audioPath) {
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

$zipName = 'prompt_' . ar_safe_file_name($promptId, 'prompt') . '_' . ($type === 'audio' ? 'audio' : 'transcripts') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tempFile));
readfile($tempFile);
@unlink($tempFile);
exit;
