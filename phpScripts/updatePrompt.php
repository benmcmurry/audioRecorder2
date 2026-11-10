<?php
include_once("../cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once("promptAudioCommon.php");

function ensureReadPromptColumn($db)
{
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    if ($columnCheck && $columnCheck->num_rows > 0) {
        return true;
    }

    $db->query("ALTER TABLE Prompts ADD COLUMN read_prompt TINYINT(1) NOT NULL DEFAULT 1");
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    return $columnCheck && $columnCheck->num_rows > 0;
}

function parseCheckboxValue($value, $defaultValue = 0)
{
    if ($value === null) {
        return (int) $defaultValue;
    }

    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, array("1", "true", "on", "yes"), true)) {
        return 1;
    }
    if (in_array($normalized, array("0", "false", "off", "no"), true)) {
        return 0;
    }

    return (int) $defaultValue;
}

$prompt_id = isset($_POST['prompt_id']) ? trim((string) $_POST['prompt_id']) : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !preg_match('/\\A[0-9]+\\z/', $prompt_id)) {
    http_response_code(400);
    exit('Invalid prompt request.');
}
if (!ar_valid_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    http_response_code(403);
    exit('Your session expired. Reload the page and try again.');
}
$ownerQuery = $elc_db->prepare('SELECT prompt_id FROM Prompts WHERE prompt_id = ? AND netid = ? LIMIT 1');
if (!$ownerQuery) {
    http_response_code(500);
    exit('Could not validate prompt ownership.');
}
$ownerQuery->bind_param('ss', $prompt_id, $netid);
$ownerQuery->execute();
$ownerResult = $ownerQuery->get_result();
if (!$ownerResult || !$ownerResult->fetch_assoc()) {
    http_response_code(403);
    exit('You do not have permission to update this prompt.');
}

$title = htmlspecialchars(isset($_POST['title']) ? (string) $_POST['title'] : '', ENT_QUOTES, 'UTF-8');
$text = htmlspecialchars(isset($_POST['text']) ? (string) $_POST['text'] : '', ENT_QUOTES, 'UTF-8');
$prepare_time = isset($_POST['prepare_time']) ? (string) $_POST['prepare_time'] : '0';
$response_time = isset($_POST['response_time']) ? (string) $_POST['response_time'] : '0';
$transcription = parseCheckboxValue(isset($_POST['transcription']) ? $_POST['transcription'] : null, 0);
$read_prompt = parseCheckboxValue(isset($_POST['read_prompt']) ? $_POST['read_prompt'] : null, 1);

$hasReadPromptColumn = ensureReadPromptColumn($elc_db);

if ($hasReadPromptColumn) {
    $query = $elc_db->prepare("Update Prompts set title = ?, text = ?, prepare_time = ?, response_time = ?, transcription = ?, read_prompt = ? where prompt_id = ? and netid = ?");
    $query->bind_param("ssssssss", $title, $text, $prepare_time, $response_time, $transcription, $read_prompt, $prompt_id, $netid);
} else {
    $query = $elc_db->prepare("Update Prompts set title = ?, text = ?, prepare_time = ?, response_time = ?, transcription = ? where prompt_id = ? and netid = ?");
    $query->bind_param("sssssss", $title, $text, $prepare_time, $response_time, $transcription, $prompt_id, $netid);
}

if ($query && $query->execute()) {
    if ($hasReadPromptColumn && (int) $read_prompt === 1) {
        $speechText = composePromptSpeechText($text, $prepare_time, $response_time);
        list($audioOk, $audioStatus) = ensurePromptAudioCached($prompt_id, $speechText);
        if (!$audioOk) {
            echo "Saved, but prompt audio was not generated: " . $audioStatus . " Students can still hear it using browser speech.";
            exit;
        }
        if ($audioStatus === "generated") {
            echo "Saved! Prompt audio generated.";
        } else {
            echo "Saved! Prompt audio unchanged, so it was not regenerated.";
        }
    } else {
        echo "Saved!";
    }
} else {
    http_response_code(500);
    echo "Could not save prompt. " . $elc_db->error;
}





?>
