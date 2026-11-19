<?php
require_once __DIR__ . '/common.php';
$handoffToken = trim((string) ($_POST['handoff'] ?? ''));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $handoffToken !== '') {
    try {
        $handoff = ar_lti_read_link_handoff($handoffToken);
    } catch (Throwable $error) {
        ar_lti_error(403, 'This Canvas account-link request expired. Start the link again from Canvas.');
    }
    $_SESSION['ar_lti_identity_key'] = $handoff['identity'];
    $_SESSION['ar_lti_launch'] = $handoff['launch'];
    $_SESSION['auth_user'] = array(
        'provider' => 'lti',
        'netid' => (string) $handoff['identity']['netid'],
        'name' => 'Canvas User',
        'email' => '',
    );
    $return = (string) $handoff['return'];
    $resourceKey = (string) ($handoff['resource_key'] ?? '');
} else {
    $return = (string) ($_GET['return'] ?? 'picker');
    $resourceKey = (string) ($_GET['resource_key'] ?? '');
}
$user = ar_lti_launch_user();
if (!$user || empty($_SESSION['ar_lti_identity_key']) || !is_array($_SESSION['ar_lti_identity_key'])) {
    ar_lti_error(403, 'Start this link from a Canvas launch.');
}
if ($return !== 'picker' && $return !== 'resource') {
    $return = 'picker';
}
if ($return === 'resource' && !preg_match('/\A[a-f0-9]{64}\z/', $resourceKey)) {
    $return = 'picker';
}
$state = bin2hex(random_bytes(32));
$_SESSION['ar_lti_pending_link'] = array(
    'state' => $state,
    'identity' => $_SESSION['ar_lti_identity_key'],
    'return' => $return,
    'resource_key' => $resourceKey,
    'created_at' => time(),
);
// Do not let the shared login broker mistake the temporary Canvas identity
// for an already authenticated campus account.
unset($_SESSION['auth_user']);
$callback = ar_lti_base_url('lti/link-return.php?state=' . rawurlencode($state));
$loginUrl = shared_auth_login_url($callback, 'audioRecorder');
header('Location: ' . $loginUrl, true, 302);
exit;
