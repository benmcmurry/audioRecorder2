<?php
include_once __DIR__ . '/auth/common.php';
require_once dirname(__DIR__) . '/sharedAuth/broker.php';

$app_root = ar_web_root();

if (isset($_REQUEST['logout'])) {
    ar_redirect($app_root . '/auth/logout.php');
}

function ar_sync_session_from_shared_auth_identity(array $identity): void
{
    $netid = isset($identity['netid']) ? trim((string) $identity['netid']) : '';
    if ($netid === '') {
        return;
    }

    $attrs = isset($identity['attributes']) && is_array($identity['attributes']) ? $identity['attributes'] : array();
    $provider = isset($identity['provider']) ? strtolower(trim((string) $identity['provider'])) : '';
    if ($provider !== 'google') {
        $provider = 'byu';
    }

    ar_set_session_user(
        $provider,
        $netid,
        isset($identity['name']) ? (string) $identity['name'] : $netid,
        isset($identity['emailAddress']) ? (string) $identity['emailAddress'] : ''
    );

    $_SESSION['preferredFirstName'] = (string) ($attrs['givenName'] ?? $attrs['preferredFirstName'] ?? (isset($identity['name']) ? (string) $identity['name'] : $netid));
    $_SESSION['surname'] = (string) ($attrs['surname'] ?? '');
}

$logoutBlocked = shared_auth_logout_blocked();
if ($logoutBlocked) {
    ar_clear_local_session();
}

$user = $logoutBlocked ? null : ar_get_session_user();
if (!$user && !$logoutBlocked) {
    $sharedIdentity = shared_auth_current_session_user();
    if (is_array($sharedIdentity)) {
        ar_sync_session_from_shared_auth_identity($sharedIdentity);
        $user = ar_get_session_user();
    }
}

if (!$user) {
    ar_auth_required_redirect();
}

$auth_provider = isset($user['provider']) ? (string) $user['provider'] : '';
$netid = isset($user['netid']) ? (string) $user['netid'] : '';
$net_id = $netid;
$name = isset($user['name']) ? (string) $user['name'] : $netid;
$email = isset($user['email']) ? (string) $user['email'] : '';
$loginName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$login = $loginName . " | <a href='" . $app_root . "/profile.php'>Profile</a> | <a href='" . $app_root . "/index.php'>Home</a> | <a href='" . $app_root . "/auth/logout.php'>Logout</a>";
