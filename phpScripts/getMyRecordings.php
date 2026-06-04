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
while ($result && ($row = $result->fetch_assoc())) {
    $hasRecordings = true;
    $tempTitle = !empty($row['title']) ? $row['title'] : 'no title';
    echo "<div class='row'>";
    echo "<div class='card m-0 p-0' id='" . ar_h($row['prompt_id']) . "'>";
    echo "<div class='card-header'><h5 style='margin:0;padding:0;'>" . ar_h($tempTitle) . "</h5>" . ar_h($row['date_created']) . "</div>";
    echo "<div class='card-body'>";
    echo "<p class='card-text'><strong>Prompt: </strong>" . ar_h($row['text']) . "</p><p>You have " . ar_h($row['prepare_time']) . " seconds to prepare and " . ar_h($row['response_time']) . " seconds to record.</p>";
    echo "<audio class='audio-control' style='padding: 0em 0em 2em;' controls preload='none'>";
    echo "<source src='" . ar_h($row['filename']) . "' type='" . ar_h($row['filetype']) . "'>";
    echo "</audio>";
    if (!empty($row['transcription_text'])) {
        echo "<p class='card-text'>Transcript: " . ar_h($row['transcription_text']) . "</p>";
    }
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

if (!$hasRecordings) {
    echo "<div class='empty-state'>No recordings found.</div>";
}
