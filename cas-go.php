<?php
include_once __DIR__ . '/auth/common.php';

$app_root = ar_web_root();

if (isset($_REQUEST['logout'])) {
    ar_redirect($app_root . '/auth/logout.php');
}

$user = ar_get_session_user();
if (!$user) {
    ar_auth_required_redirect();
}

$auth_provider = isset($user['provider']) ? $user['provider'] : '';
$netid = isset($user['netid']) ? $user['netid'] : '';
$net_id = $netid;
$name = isset($user['name']) ? $user['name'] : $netid;
$email = isset($user['email']) ? $user['email'] : '';

$loginName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$providerTag = strtoupper($auth_provider);
$login = $loginName . " ({$providerTag}) | <a href='" . $app_root . "/profile.php'>Profile</a> | <a href='" . $app_root . "/auth/logout.php'>Logout</a>";
?>
