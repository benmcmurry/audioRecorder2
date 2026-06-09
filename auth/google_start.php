<?php
include_once __DIR__ . '/common.php';

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'login';
if ($mode !== 'link') {
    $mode = 'login';
}

if (!ar_google_shared_enabled()) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Google sign-in is not configured.'));
}

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);

if ($mode === 'link') {
    $user = ar_get_session_user();
    if (!$user || !isset($user['netid']) || trim((string) $user['netid']) === '') {
        ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Linking requires a signed-in account first.'));
    }
    $_SESSION['google_link_target_netid'] = $user['netid'];
}

$state = ar_random_string(40);
$_SESSION['ar_google_login'] = array(
    'state' => $state,
    'mode' => $mode,
    'redirect' => $redirect,
    'created' => time()
);

$sharedStartUrl = rtrim(ar_google_shared_root(), '/') . '/google_start.php';
$url = ar_build_url_with_query($sharedStartUrl, array(
    'app' => ar_google_app_id(),
    'return_to' => ar_google_consume_url(),
    'state' => $state
));

ar_redirect($url);
