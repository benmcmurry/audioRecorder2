<?php
function ar_activity_valid_id($activityId)
{
    return is_string($activityId) && preg_match('/\\A[a-f0-9]{32}\\z/i', $activityId) === 1;
}

function ar_activity_owned($db, $activityId, $netid)
{
    if (!ar_activity_valid_id($activityId)) {
        return null;
    }

    $query = $db->prepare('SELECT activity_id, netid, title, archive, date_created FROM Activities WHERE activity_id = ? AND netid = ? LIMIT 1');
    if (!$query) {
        return null;
    }
    $query->bind_param('ss', $activityId, $netid);
    $query->execute();
    $result = $query->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function ar_activity_items($db, $activityId)
{
    ar_activity_ensure_read_prompt_column($db);
    $query = $db->prepare('SELECT Activity_items.item_id, Activity_items.activity_id, Activity_items.position, Activity_items.prompt_id, Activity_items.source_prompt_id, Prompts.title, Prompts.text, Prompts.prepare_time, Prompts.response_time, Prompts.transcription, Prompts.read_prompt FROM Activity_items JOIN Prompts ON Prompts.prompt_id = Activity_items.prompt_id WHERE Activity_items.activity_id = ? ORDER BY Activity_items.position ASC, Activity_items.item_id ASC');
    if (!$query) {
        return array();
    }
    $query->bind_param('s', $activityId);
    $query->execute();
    $result = $query->get_result();
    $items = array();
    while ($result && $row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    return $items;
}

function ar_activity_prompt_has_responses($db, $activityId)
{
    $query = $db->prepare('SELECT COUNT(*) AS response_count FROM Activity_items JOIN Audio_files ON Audio_files.prompt_id = Activity_items.prompt_id WHERE Activity_items.activity_id = ?');
    if (!$query) {
        return true;
    }
    $query->bind_param('s', $activityId);
    $query->execute();
    $result = $query->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    return !$row || (int) $row['response_count'] > 0;
}

function ar_activity_json_response($statusCode, $payload)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

function ar_activity_ensure_read_prompt_column($db)
{
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    if ($columnCheck && $columnCheck->num_rows > 0) {
        return true;
    }
    $db->query('ALTER TABLE Prompts ADD COLUMN read_prompt TINYINT(1) NOT NULL DEFAULT 1');
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    return $columnCheck && $columnCheck->num_rows > 0;
}
