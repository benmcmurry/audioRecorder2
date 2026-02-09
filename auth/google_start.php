<?php
include_once __DIR__ . '/common.php';

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'login';
if ($mode !== 'link') {
    $mode = 'login';
}

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);

if ($mode === 'link') {
    $user = ar_get_session_user();
    if (!$user || !isset($user['provider']) || $user['provider'] !== 'cas') {
        ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Linking requires a CAS login first.'));
    }
    $_SESSION['google_link_target_netid'] = $user['netid'];
}

$state = ar_random_string(40);
$nonce = ar_random_string(40);
$_SESSION['oauth_state'] = array(
    'value' => $state,
    'nonce' => $nonce,
    'mode' => $mode,
    'redirect' => $redirect,
    'created' => time()
);

$params = array(
    'client_id' => ar_google_client_id(),
    'redirect_uri' => ar_google_redirect_uri(),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'nonce' => $nonce,
    'prompt' => 'select_account'
);

ar_redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&'));
