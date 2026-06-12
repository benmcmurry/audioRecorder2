<?php
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');

function ar_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$query = $elc_db->prepare("SELECT Audio_files.prompt_id, Audio_files.netid, Audio_files.filename, Audio_files.filetype, Audio_files.transcription_text, Audio_files.date_created, Prompts.title, Prompts.text, Prompts.prepare_time, Prompts.response_time FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.netid = ? ORDER BY Audio_files.date_created DESC");
if (!$query) {
    http_response_code(500);
    echo "<div class='alert alert-danger'>Could not load recordings.</div>";
    exit;
}

$query->bind_param("s", $netid);
$query->execute();
$result = $query->get_result();

echo "<p>Recordings for " . ar_h($name) . ".</p>";

$hasRecordings = false;
$recordIndex = 0;
while ($result && ($row = $result->fetch_assoc())) {
    $hasRecordings = true;
    $recordIndex++;
    $tempTitle = !empty($row['title']) ? $row['title'] : 'no title';
    $cardHeadingId = 'recording-heading-' . (int) $row['prompt_id'] . '-' . $recordIndex;
    $audioLabel = $tempTitle . ', ' . $row['date_created'];
    $dateTimeValue = '';
    if (!empty($row['date_created'])) {
        $timestamp = strtotime((string) $row['date_created']);
        if ($timestamp !== false) {
            $dateTimeValue = date('c', $timestamp);
        }
    }
    echo "<div class='row'>";
    echo "<article class='card m-0 p-0' id='" . ar_h($row['prompt_id']) . "' aria-labelledby='" . ar_h($cardHeadingId) . "'>";
    echo "<div class='card-header'><h5 id='" . ar_h($cardHeadingId) . "' style='margin:0;padding:0;'>" . ar_h($tempTitle) . ", " . ar_h($row['date_created']) . "</h5>";
    if ($dateTimeValue !== '') {
        echo "<time class='d-block text-muted small' datetime='" . ar_h($dateTimeValue) . "'>" . ar_h($row['date_created']) . "</time>";
    }
    echo "</div>";
    echo "<div class='card-body'>";
    echo "<p class='card-text'><strong>Prompt: </strong>" . ar_h($row['text']) . "</p><p>You have " . ar_h($row['prepare_time']) . " seconds to prepare and " . ar_h($row['response_time']) . " seconds to record.</p>";
    echo "<audio class='audio-control' style='padding: 0em 0em 2em;' controls preload='none' aria-label='" . ar_h('Play recording for ' . $audioLabel) . "'>";
    echo "<source src='" . ar_h($row['filename']) . "' type='" . ar_h($row['filetype']) . "'>";
    echo "</audio>";
    if (!empty($row['transcription_text'])) {
        echo "<p class='card-text'>Transcript: " . ar_h($row['transcription_text']) . "</p>";
    } else {
        echo "<p class='card-text text-muted'>Transcript unavailable.</p>";
    }
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

if (!$hasRecordings) {
    echo "<div class='empty-state'>No recordings found.</div>";
}
