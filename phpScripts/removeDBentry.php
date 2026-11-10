<?php

include_once("../cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
if (!ar_valid_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    http_response_code(403);
    exit('Your session expired. Reload the page and try again.');
}

$promptId = isset($_POST['prompt_id']) ? trim((string) $_POST['prompt_id']) : '';
if (!preg_match('/\\A[0-9]+\\z/', $promptId)) {
    http_response_code(400);
    exit('Invalid prompt.');
}

$query = $elc_db->prepare("DELETE FROM Audio_files WHERE prompt_id = ? AND netid = ?");
$query->bind_param('ss', $promptId, $netid);
$query->execute();
echo "Please refresh this page to re-record your response.";

?>
