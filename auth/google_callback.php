<?php
include_once __DIR__ . '/common.php';

if (!isset($_GET['state']) || !isset($_GET['token'])) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Missing Google authorization data.'));
}

if (!isset($_SESSION['ar_google_login']) || !is_array($_SESSION['ar_google_login'])) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Google login session is missing.'));
}

$oauthState = $_SESSION['ar_google_login'];
unset($_SESSION['ar_google_login']);

if (!isset($oauthState['state']) || !hash_equals((string) $oauthState['state'], (string) $_GET['state'])) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Invalid Google state.'));
}

$claims = ar_verify_google_token((string) $_GET['token']);
if (!$claims) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Google sign-in could not be verified.'));
}

$sub = isset($claims['sub']) ? (string) $claims['sub'] : '';
$email = isset($claims['email']) ? (string) $claims['email'] : '';
$name = isset($claims['name']) ? (string) $claims['name'] : $email;
$mode = isset($oauthState['mode']) ? $oauthState['mode'] : 'login';
$redirect = isset($oauthState['redirect']) ? $oauthState['redirect'] : (ar_web_root() . '/index.php');

if ($mode === 'link') {
    $targetNetid = isset($_SESSION['google_link_target_netid']) ? $_SESSION['google_link_target_netid'] : '';
    unset($_SESSION['google_link_target_netid']);
    if ($targetNetid === '') {
        ar_redirect(ar_web_root() . '/profile.php?error=' . urlencode('CAS session required to link Google.'));
    }

    $existingNetid = ar_find_google_link($sub);
    if ($existingNetid !== null && $existingNetid !== $targetNetid) {
        if (strpos($existingNetid, 'google_') === 0) {
            ar_migrate_netid_data($existingNetid, $targetNetid);
        } else {
            ar_redirect(ar_web_root() . '/profile.php?error=' . urlencode('This Google account is already linked to another user.'));
        }
    }

    ar_set_google_link($sub, $targetNetid, $email);
    ar_redirect(ar_web_root() . '/profile.php?success=' . urlencode('Google account connected successfully.'));
}

$linkedNetid = ar_find_google_link($sub);
if ($linkedNetid !== null) {
    $netid = $linkedNetid;
} else {
    $netid = ar_google_subject_to_netid($sub);
    ar_set_google_link($sub, $netid, $email);
}

ar_upsert_user($netid, $name);
ar_set_session_user('google', $netid, $name, $email);
ar_redirect($redirect);
