<?php

include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once("promptAudioCommon.php");

header("Cache-Control: no-store, max-age=0");
ini_set("display_errors", "0");
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

function json_error($statusCode, $message)
{
    http_response_code($statusCode);
    header("Content-Type: application/json");
    echo json_encode(array("error" => $message));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    json_error(405, "Method not allowed. Use GET.");
}

if (!isset($_GET["prompt_id"])) {
    json_error(400, "Missing required query param: prompt_id");
}

$promptId = preg_replace("/[^0-9]/", "", (string) $_GET["prompt_id"]);
if ($promptId === "") {
    json_error(400, "Invalid prompt_id");
}

function hasReadPromptColumn($db)
{
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    return $columnCheck && $columnCheck->num_rows > 0;
}

$promptQuerySql = "SELECT prompt_id, text, prepare_time, response_time";
if (hasReadPromptColumn($elc_db)) {
    $promptQuerySql .= ", read_prompt";
}
$promptQuerySql .= " FROM Prompts WHERE prompt_id = ? LIMIT 1";

$query = $elc_db->prepare($promptQuerySql);
if (!$query) {
    json_error(500, "Could not prepare prompt lookup query.");
}

$query->bind_param("s", $promptId);
$query->execute();
$result = $query->get_result();
$promptRow = $result ? $result->fetch_assoc() : null;
if (!$promptRow) {
    json_error(404, "Prompt not found.");
}

$readPrompt = 1;
if (isset($promptRow["read_prompt"])) {
    $readPrompt = (int) $promptRow["read_prompt"];
}
if ($readPrompt !== 1) {
    json_error(404, "Prompt read-aloud is disabled.");
}

$paths = promptAudioPaths($promptId);
if (!is_file($paths["audio"])) {
    json_error(404, "Prompt audio not found.");
}

header("Content-Type: audio/mpeg");
header("Content-Disposition: inline; filename=prompt_" . $promptId . ".mp3");
readfile($paths["audio"]);
exit;

?>
