<?php
include_once __DIR__ . '/common.php';
ar_auth_debug_log('login.php');

$currentUser = ar_get_session_user();
if ($currentUser) {
    ar_redirect(ar_web_root() . '/index.php');
}

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);
$error = isset($_GET['error']) ? $_GET['error'] : '';
$googleEnabled = ar_google_shared_enabled();
$oktaEnabled = shared_auth_okta_enabled();

if (shared_auth_dev_enabled()) {
    shared_auth_redirect(shared_auth_login_url($redirect));
}

shared_auth_render_login_choice(
    'ELC Audio Recorder',
    'Sign in',
    'Use your BYU account, Okta, or Google to continue.',
    array(
        array(
            'provider' => 'cas',
            'label' => 'Continue with CAS',
            'enabled' => true,
            'url' => ar_web_root() . '/auth/cas_start.php?redirect=' . urlencode($redirect),
            'disabled_label' => 'Authentication unavailable',
        ),
        array(
            'provider' => 'google',
            'label' => 'Continue with Google',
            'enabled' => $googleEnabled,
            'url' => ar_web_root() . '/auth/google_start.php?mode=login&redirect=' . urlencode($redirect),
            'disabled_label' => 'Google unavailable',
        ),
        array(
            'provider' => 'okta',
            'label' => 'Continue with Okta',
            'enabled' => $oktaEnabled,
            'url' => shared_auth_build_url_with_query(shared_auth_base_url() . '/okta_start.php', array(
                'return_to' => ar_web_root() . '/auth/login.php?redirect=' . urlencode($redirect)
            )),
            'disabled_label' => 'Okta unavailable',
        ),
    ),
    'If one option is unavailable, check server auth configuration.',
    $error,
    ''
);
?>
