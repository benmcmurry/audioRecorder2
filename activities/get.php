<?php
include_once('../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('../phpScripts/activityHelpers.php');

$activityId = trim((string) ($_GET['activity_id'] ?? ''));
if (!ar_activity_valid_id($activityId)) {
    ar_activity_json_response(400, array('ok' => false, 'message' => 'Invalid activity link.'));
}
$activityQuery = $elc_db->prepare('SELECT activity_id, title, archive FROM Activities WHERE activity_id = ? LIMIT 1');
$activityQuery->bind_param('s', $activityId);
$activityQuery->execute();
$activityResult = $activityQuery->get_result();
$activity = $activityResult ? $activityResult->fetch_assoc() : null;
if (!$activity || (int) $activity['archive'] !== 0) {
    ar_activity_json_response(404, array('ok' => false, 'message' => 'This activity is unavailable.'));
}
$steps = array();
foreach (ar_activity_items($elc_db, $activityId) as $item) {
    $response = null;
    if (!empty($netid)) {
        $responseQuery = $elc_db->prepare('SELECT id, transcription_status, transcription_text FROM Audio_files WHERE prompt_id = ? AND netid = ? ORDER BY date_created DESC, id DESC LIMIT 1');
        $promptId = (string) $item['prompt_id'];
        $responseQuery->bind_param('ss', $promptId, $netid);
        $responseQuery->execute();
        $responseResult = $responseQuery->get_result();
        $response = $responseResult ? $responseResult->fetch_assoc() : null;
    }
    $steps[] = array(
        'item_id' => (int) $item['item_id'],
        'position' => (int) $item['position'],
        'prompt_id' => (int) $item['prompt_id'],
        'title' => (string) $item['title'],
        'text' => (string) $item['text'],
        'prepare_time' => (int) $item['prepare_time'],
        'response_time' => (int) $item['response_time'],
        'transcription' => (int) $item['transcription'],
        'read_prompt' => isset($item['read_prompt']) ? (int) $item['read_prompt'] : 1,
        'completed' => (bool) $response,
        'response_id' => $response ? (int) $response['id'] : 0,
        'transcription_status' => $response ? (string) $response['transcription_status'] : '',
        'transcription_text' => $response ? (string) $response['transcription_text'] : '',
    );
}
ar_activity_json_response(200, array(
    'ok' => true,
    'activity_id' => $activityId,
    'title' => (string) $activity['title'],
    'signed_in' => !empty($netid),
    'items' => $steps,
));
