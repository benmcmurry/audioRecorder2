<?php
function ar_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ar_safe_file_name($value, $fallback)
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $value);
    $value = trim($value, '_');
    return $value === '' ? $fallback : $value;
}

function ar_download_error($statusCode, $message)
{
    http_response_code($statusCode);
    echo $message;
    exit;
}

function ar_audio_file_path($filename)
{
    $appRoot = realpath(__DIR__ . '/..');
    $audioPath = realpath($appRoot . '/' . ltrim((string) $filename, '/'));
    if (!$audioPath || strpos($audioPath, $appRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($audioPath)) {
        return false;
    }

    return $audioPath;
}

function ar_prompt_for_owner($db, $promptId, $netid)
{
    $query = $db->prepare("SELECT prompt_id, netid, title, text, prepare_time, response_time, transcription, read_prompt, archive, date_created FROM Prompts WHERE prompt_id = ? AND netid = ? LIMIT 1");
    $query->bind_param("ss", $promptId, $netid);
    $query->execute();
    $result = $query->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function ar_prompt_responses($db, $promptId)
{
    $query = $db->prepare("SELECT Audio_files.id, Audio_files.prompt_id, Audio_files.netid, Audio_files.filename, Audio_files.filesize, Audio_files.filetype, Audio_files.transcription_text, Audio_files.date_created, Users.name AS user_name FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid WHERE Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC, Audio_files.date_created DESC");
    $query->bind_param("s", $promptId);
    $query->execute();
    $result = $query->get_result();

    $responses = array();
    while ($result && $row = $result->fetch_assoc()) {
        $responses[] = $row;
    }

    return $responses;
}

function ar_response_for_download($db, $responseId)
{
    $query = $db->prepare("SELECT Audio_files.id, Audio_files.prompt_id, Audio_files.netid, Audio_files.filename, Audio_files.filesize, Audio_files.filetype, Audio_files.transcription_text, Audio_files.date_created, Users.name AS user_name, Prompts.netid AS prompt_owner FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.id = ? LIMIT 1");
    $query->bind_param("i", $responseId);
    $query->execute();
    $result = $query->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function ar_student_name($row)
{
    return isset($row['user_name']) && $row['user_name'] ? $row['user_name'] : $row['netid'];
}
