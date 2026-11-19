<?php
require_once __DIR__ . '/common.php';
$ltiUser = ar_lti_launch_user();
$state = (string) ($_GET['state'] ?? '');
$pending = $_SESSION['ar_lti_pending_link'] ?? null;
unset($_SESSION['ar_lti_pending_link']);
if (!is_array($pending) || !preg_match('/\A[a-f0-9]{64}\z/', $state)
    || !hash_equals((string) ($pending['state'] ?? ''), $state)
    || (int) ($pending['created_at'] ?? 0) < time() - 900
    || !is_array($pending['identity'] ?? null)) {
    ar_lti_error(403, 'Account linking expired. Start the link from Canvas again.');
}
$identity = shared_auth_current_session_user();
if (!$identity && function_exists('ar_get_session_user')) {
    $identity = ar_get_session_user();
}
$netid = is_array($identity) ? trim((string) ($identity['netid'] ?? '')) : '';
$provider = is_array($identity) ? strtolower(trim((string) ($identity['provider'] ?? ''))) : '';
if ($netid === '' || $provider === 'lti') {
    ar_lti_error(403, 'Sign in to your existing account before linking Canvas. The email address alone is not enough to link accounts.');
}
$key = $pending['identity'];
$issuer = rtrim((string) ($key['issuer'] ?? ''), '/');
$deploymentId = (string) ($key['deployment_id'] ?? '');
$subject = (string) ($key['subject'] ?? '');
$oldNetid = (string) ($key['netid'] ?? '');
$identityHash = hash('sha256', $issuer . "\0" . $deploymentId . "\0" . $subject);
if ($netid === $oldNetid) {
    $linkedNetid = $netid;
} else {
    $db = ar_lti_db();
    $db->begin_transaction();
    try {
        $collision = $db->prepare('SELECT a.id FROM Audio_files a JOIN Audio_files b ON b.prompt_id = a.prompt_id WHERE a.netid = ? AND b.netid = ? LIMIT 1');
        if ($collision) {
            $collision->bind_param('ss', $oldNetid, $netid);
            $collision->execute();
            if ($collision->get_result()->fetch_assoc()) {
                throw new RuntimeException('This Canvas account already has recordings on one or more of the same prompts, so the accounts cannot be merged automatically. Contact support to resolve the overlap.');
            }
        }
        $updateIdentity = $db->prepare('UPDATE Lti_identities SET netid = ? WHERE identity_hash = ? AND netid = ?');
        if (!$updateIdentity) {
            throw new RuntimeException('Canvas identity storage is unavailable.');
        }
        $updateIdentity->bind_param('sss', $netid, $identityHash, $oldNetid);
        if (!$updateIdentity->execute() || $updateIdentity->affected_rows !== 1) {
            throw new RuntimeException('The Canvas identity could not be linked.');
        }
        foreach (array('Audio_files', 'Prompts', 'Activities') as $table) {
            $move = $db->prepare('UPDATE ' . $table . ' SET netid = ? WHERE netid = ?');
            if (!$move) {
                throw new RuntimeException('Account linking requires the activity database migration.');
            }
            $move->bind_param('ss', $netid, $oldNetid);
            if (!$move->execute()) {
                throw new RuntimeException('Existing Canvas recordings could not be moved.');
            }
        }
        $moveResources = $db->prepare('UPDATE Lti_resources SET owner_netid = ? WHERE owner_netid = ?');
        $moveResources->bind_param('ss', $netid, $oldNetid);
        if (!$moveResources->execute()) {
            throw new RuntimeException('Canvas assignment links could not be linked to the account.');
        }
        $moveSubmissions = $db->prepare('UPDATE Lti_submission_links SET netid = ? WHERE netid = ?');
        $moveSubmissions->bind_param('ss', $netid, $oldNetid);
        if (!$moveSubmissions->execute()) {
            throw new RuntimeException('Canvas submission links could not be linked to the account.');
        }
        $db->commit();
        $linkedNetid = $netid;
    } catch (Throwable $error) {
        $db->rollback();
        ar_lti_error(409, $error->getMessage());
    }
}
$_SESSION['auth_user'] = array(
    'provider' => 'lti',
    'netid' => $linkedNetid,
    'name' => (string) ($identity['name'] ?? (is_array($ltiUser) ? ($ltiUser['name'] ?? 'Canvas User') : 'Canvas User')),
    'email' => '',
);
$_SESSION['ar_lti_identity_key']['netid'] = $linkedNetid;
if (($pending['return'] ?? '') === 'resource' && !empty($pending['resource_key'])) {
    $_SESSION['ar_lti_link_prompted'] = true;
    header('Location: ' . ar_lti_base_url('lti/resource.php?resource_key=' . rawurlencode((string) $pending['resource_key'])));
} else {
    header('Location: ' . ar_lti_base_url('lti/content-picker.php'));
}
exit;
