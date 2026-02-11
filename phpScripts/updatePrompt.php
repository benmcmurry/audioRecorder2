<?php
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
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

$prompt_id = $_POST['prompt_id'];
$title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
$text = htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8');
$prepare_time = $_POST['prepare_time'];
$response_time = $_POST['response_time'];
$transcription = parseCheckboxValue(isset($_POST['transcription']) ? $_POST['transcription'] : null, 0);
$read_prompt = parseCheckboxValue(isset($_POST['read_prompt']) ? $_POST['read_prompt'] : null, 1);

$hasReadPromptColumn = ensureReadPromptColumn($elc_db);

if ($hasReadPromptColumn) {
    $query = $elc_db->prepare("Update Prompts set title = ?, text = ?, prepare_time = ?, response_time = ?, transcription = ?, read_prompt = ? where prompt_id = ?");
    $query->bind_param("sssssss", $title, $text, $prepare_time, $response_time, $transcription, $read_prompt, $prompt_id);
} else {
    $query = $elc_db->prepare("Update Prompts set title = ?, text = ?, prepare_time = ?, response_time = ?, transcription = ? where prompt_id = ?");
    $query->bind_param("ssssss", $title, $text, $prepare_time, $response_time, $transcription, $prompt_id);
}

if ($query && $query->execute()) {
    if ($hasReadPromptColumn && (int) $read_prompt === 1) {
        $speechText = composePromptSpeechText($text, $prepare_time, $response_time);
        list($audioOk, $audioStatus) = ensurePromptAudioCached($prompt_id, $speechText);
        if (!$audioOk) {
            http_response_code(500);
            echo "Saved prompt text, but prompt audio generation failed. " . $audioStatus;
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
