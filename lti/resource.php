<?php
require_once __DIR__ . '/common.php';
$user = ar_lti_launch_user();
$launch = $_SESSION['ar_lti_launch'] ?? null;
$resourceKey = (string) ($_GET['resource_key'] ?? '');
if (!$user || !is_array($launch) || !preg_match('/\A[a-f0-9]{64}\z/', $resourceKey)) {
    ar_lti_error(403, 'Open this assignment from Canvas.');
}
$keyHash = hash('sha256', $resourceKey);
$db = ar_lti_db();
$query = $db->prepare('SELECT owner_netid, target_type, target_id, title FROM Lti_resources WHERE resource_key_hash = ? AND issuer = ? AND deployment_id = ? LIMIT 1');
if (!$query) {
    ar_lti_error(500, 'Canvas resource storage is not initialized.');
}
$issuer = (string) $launch['issuer'];
$deploymentId = (string) $launch['deployment_id'];
$query->bind_param('sss', $keyHash, $issuer, $deploymentId);
$query->execute();
$result = $query->get_result();
$resource = $result ? $result->fetch_assoc() : null;
if (!$resource) {
    ar_lti_error(404, 'This Audio Recorder assignment is no longer available.');
}
if ($resource['target_type'] === 'prompt') {
    $check = $db->prepare('SELECT prompt_id FROM Prompts WHERE prompt_id = ? AND netid = ? AND archive = 0 LIMIT 1');
    $check->bind_param('ss', $resource['target_id'], $resource['owner_netid']);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        ar_lti_error(404, 'This prompt has been archived or removed.');
    }
    $targetUrl = 'index.php?prompt_id=' . rawurlencode((string) $resource['target_id']);
} else {
    $check = $db->prepare('SELECT activity_id FROM Activities WHERE activity_id = ? AND netid = ? AND archive = 0 LIMIT 1');
    $check->bind_param('ss', $resource['target_id'], $resource['owner_netid']);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        ar_lti_error(404, 'This activity has been archived or removed.');
    }
    $targetUrl = 'index.php?activity_id=' . rawurlencode((string) $resource['target_id']);
}
if (empty($_GET['continue']) && empty($_SESSION['ar_lti_link_prompted'])) {
    $_SESSION['ar_lti_pending_resource'] = $resourceKey;
    $isSynthetic = strpos((string) $user['netid'], 'lti_') === 0;
    $linkHandoffToken = $isSynthetic ? ar_lti_create_link_handoff((array) ($_SESSION['ar_lti_identity_key'] ?? array()), (array) ($_SESSION['ar_lti_launch'] ?? array()), 'resource', $resourceKey) : '';
    ar_lti_html_start('Canvas assignment');
    ?>
    <section class="dashboard-card">
        <p class="section-kicker">Canvas assignment</p>
        <h1><?php echo htmlspecialchars((string) $resource['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Continue to the Audio Recorder inside Canvas, or link this Canvas identity to an existing Audio Recorder account to use its prompt library and history.</p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars(ar_lti_base_url('lti/resource.php?resource_key=' . rawurlencode($resourceKey) . '&continue=1'), ENT_QUOTES, 'UTF-8'); ?>">Continue to assignment</a>
            <?php if ($isSynthetic) { ?><form method="post" action="<?php echo htmlspecialchars(ar_lti_base_url('lti/link.php'), ENT_QUOTES, 'UTF-8'); ?>" target="_top"><input type="hidden" name="handoff" value="<?php echo htmlspecialchars($linkHandoffToken, ENT_QUOTES, 'UTF-8'); ?>"><button class="btn btn-outline-primary" type="submit">Link existing account</button></form><?php } ?>
        </div>
    </section>
    <?php
    ar_lti_html_end();
    exit;
}
$_SESSION['ar_lti_link_prompted'] = true;
header('Location: ' . ar_lti_base_url($targetUrl));
exit;
