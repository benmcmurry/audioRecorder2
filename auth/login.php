<?php
include_once __DIR__ . '/common.php';
ar_auth_debug_log('login.php');

$logoutBlocked = shared_auth_logout_blocked();
if ($logoutBlocked) {
    ar_clear_local_session();
}

$currentUser = ar_get_session_user();
if (!$logoutBlocked && !$currentUser && shared_auth_current_session_user()) {
    ar_redirect(ar_web_root() . '/index.php');
}

if (!$logoutBlocked && $currentUser) {
    ar_redirect(ar_web_root() . '/index.php');
}

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);

ar_redirect(shared_auth_login_url($redirect, 'audioRecorder'));
?>
