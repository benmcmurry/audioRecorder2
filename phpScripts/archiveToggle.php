<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("../cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');

$promptId = isset($_POST['prompt_id']) ? trim((string) $_POST['prompt_id']) : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !preg_match('/\\A[0-9]+\\z/', $promptId)) {
    http_response_code(400);
    exit('Invalid request.');
}

$archiveStatus = isset($_POST['archiveStatus']) ? $_POST['archiveStatus'] : '';
if (!in_array($archiveStatus, array('current', 'archived'), true)) {
    http_response_code(400);
    exit('Invalid archive status.');
}

if ($archiveStatus === "current") {
    $archiveStatus = 1;
} else {
    $archiveStatus = 0;
}
$query = $elc_db->prepare("UPDATE Prompts SET archive = ? WHERE prompt_id = ? AND netid = ?");
$query->bind_param('sss', $archiveStatus, $promptId, $netid);
$query->execute();
