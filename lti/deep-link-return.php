<?php
require_once __DIR__ . '/common.php';
$launch = $_SESSION['ar_lti_launch'] ?? null;
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_array($launch) || !ar_lti_has_teacher_role($launch) || !ar_lti_launch_user()) {
    ar_lti_error(403, 'This Canvas content selection is unavailable. Start again from the assignment setup screen.');
}
$csrf = (string) ($_POST['csrf'] ?? '');
if (!isset($_SESSION['ar_lti_picker_csrf']) || !hash_equals((string) $_SESSION['ar_lti_picker_csrf'], $csrf)) {
    ar_lti_error(403, 'This content selection expired. Start again from Canvas.');
}
unset($_SESSION['ar_lti_picker_csrf']);
$kind = (string) ($_POST['kind'] ?? '');
$netid = (string) ar_lti_launch_user()['netid'];
$db = ar_lti_db();
$title = '';
$targetId = '';
if ($kind === 'prompt') {
    $promptValue = trim((string) ($_POST['target_id'] ?? ''));
    if (!preg_match('/\A[0-9]+\z/', $promptValue)) {
        ar_lti_error(400, 'Select exactly one prompt for this assignment.');
    }
    $promptId = $promptValue;
    $query = $db->prepare('SELECT prompt_id, title FROM Prompts WHERE prompt_id = ? AND netid = ? AND archive = 0 AND prompt_id NOT IN (SELECT prompt_id FROM Activity_items) LIMIT 1');
    $query->bind_param('ss', $promptId, $netid);
    $query->execute();
    $row = $query->get_result()->fetch_assoc();
    if (!$row) {
        ar_lti_error(403, 'That prompt is not available in this account.');
    }
    $targetId = (string) $row['prompt_id'];
    $title = trim((string) ($row['title'] ?? '')) ?: 'Audio Recorder prompt';
} elseif ($kind === 'new_prompt') {
    $promptTitle = trim((string) ($_POST['title'] ?? ''));
    $promptText = trim((string) ($_POST['text'] ?? ''));
    $prepare = filter_var($_POST['prepare_time'] ?? null, FILTER_VALIDATE_INT);
    $responseTime = filter_var($_POST['response_time'] ?? null, FILTER_VALIDATE_INT);
    if ($promptTitle === '' || strlen($promptTitle) > 255 || $promptText === '' || strlen($promptText) > 10000 || $prepare === false || $responseTime === false || $prepare < 0 || $prepare > 600 || $responseTime < 1 || $responseTime > 600) {
        ar_lti_error(400, 'Enter a title, prompt, and valid preparation and response times.');
    }
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    if (!$columnCheck || $columnCheck->num_rows === 0) {
        if (!$db->query('ALTER TABLE Prompts ADD COLUMN read_prompt TINYINT(1) NOT NULL DEFAULT 1')) {
            ar_lti_error(500, 'Prompt settings are not ready. Apply the Audio Recorder activity migration.');
        }
    }
    $insert = $db->prepare('INSERT INTO Prompts (prompt_id, netid, title, text, prepare_time, response_time, transcription, read_prompt, archive, date_created) VALUES (NULL, ?, ?, ?, ?, ?, 0, 1, 0, NOW())');
    if (!$insert) {
        ar_lti_error(500, 'The prompt could not be created.');
    }
    $prep = (string) $prepare;
    $response = (string) $responseTime;
    $insert->bind_param('sssss', $netid, $promptTitle, $promptText, $prep, $response);
    if (!$insert->execute()) {
        ar_lti_error(500, 'The prompt could not be created.');
    }
    $targetId = (string) $insert->insert_id;
    $title = $promptTitle;
    $kind = 'prompt';
} elseif ($kind === 'activity') {
    $targetId = trim((string) ($_POST['target_id'] ?? ''));
    if (!preg_match('/\A[a-f0-9]{32}\z/', $targetId)) {
        ar_lti_error(400, 'Choose a saved activity.');
    }
    $query = $db->prepare('SELECT Activities.activity_id, Activities.title FROM Activities WHERE Activities.activity_id = ? AND Activities.netid = ? AND Activities.archive = 0 LIMIT 1');
    $query->bind_param('ss', $targetId, $netid);
    $query->execute();
    $row = $query->get_result()->fetch_assoc();
    if (!$row) {
        ar_lti_error(403, 'That activity is not available in this account.');
    }
    $title = trim((string) $row['title']) ?: 'Audio Recorder activity';
} else {
    ar_lti_error(400, 'Choose a prompt or activity.');
}

$resourceKey = bin2hex(random_bytes(32));
$resourceKeyHash = hash('sha256', $resourceKey);
$platform = $_SESSION['ar_lti_platform'] ?? array();
$insert = $db->prepare('INSERT INTO Lti_resources (resource_key_hash, issuer, deployment_id, owner_netid, target_type, target_id, title, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
if (!$insert) {
    ar_lti_error(500, 'Canvas resource storage is unavailable. Apply the LTI database migration.');
}
$insert->bind_param('sssssss', $resourceKeyHash, $platform['issuer'], $platform['deployment_id'], $netid, $kind, $targetId, $title);
if (!$insert->execute()) {
    ar_lti_error(500, 'The Canvas assignment link could not be saved.');
}

$settings = $launch['deep_link_settings'] ?? array();
$item = array(
    'type' => 'ltiResourceLink',
    'title' => $title,
    'url' => ar_lti_base_url('lti/launch.php?resource_key=' . rawurlencode($resourceKey)),
    'custom' => array('resource_key' => $resourceKey),
);
if (!empty($settings['accept_lineitem'])) {
    $item['lineItem'] = array(
        'scoreMaximum' => 100,
        'label' => $title,
        'resourceId' => $resourceKey,
        'tag' => 'audio-recorder',
    );
}
$now = time();
$responseClaims = array(
    'iss' => (string) $platform['client_id'],
    'aud' => (string) $platform['issuer'],
    'azp' => (string) $platform['client_id'],
    'iat' => $now,
    'exp' => $now + 300,
    'nonce' => (string) ($launch['nonce'] ?? ''),
    'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiDeepLinkingResponse',
    'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
    'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => (string) $platform['deployment_id'],
    'https://purl.imsglobal.org/spec/lti-dl/claim/content_items' => array($item),
);
if (!empty($settings['data'])) {
    $responseClaims['https://purl.imsglobal.org/spec/lti-dl/claim/data'] = (string) $settings['data'];
}
try {
    $signed = ar_lti_sign_jwt($responseClaims);
    $returnUrl = (string) ($settings['deep_link_return_url'] ?? '');
    if (strpos($returnUrl, 'https://') !== 0) {
        throw new RuntimeException('Canvas deep-link return URL is invalid.');
    }
} catch (Throwable $error) {
    error_log('[audioRecorder-lti] Deep Linking response failed: ' . $error->getMessage());
    ar_lti_error(500, 'The Canvas content selection could not be completed. Check the Audio Recorder LTI signing configuration.');
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
$safeReturnUrl = htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8');
$safeJwt = htmlspecialchars($signed, ENT_QUOTES, 'UTF-8');
$safeState = htmlspecialchars((string) ($_POST['state'] ?? ''), ENT_QUOTES, 'UTF-8');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Returning to Canvas</title></head><body><p>Returning your selection to Canvas…</p><form id="return" method="post" action="<?php echo $safeReturnUrl; ?>"><input type="hidden" name="JWT" value="<?php echo $safeJwt; ?>"><input type="hidden" name="state" value="<?php echo $safeState; ?>"><button type="submit">Return to Canvas</button></form><script>document.getElementById('return').submit();</script></body></html>
