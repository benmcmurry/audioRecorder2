<?php
include_once __DIR__ . '/common.php';

if (!isset($_GET['state']) || !isset($_GET['code'])) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Missing Google authorization data.'));
}

if (!isset($_SESSION['oauth_state']) || !is_array($_SESSION['oauth_state'])) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('OAuth session is missing.'));
}

$oauthState = $_SESSION['oauth_state'];
unset($_SESSION['oauth_state']);

if (!isset($oauthState['value']) || $_GET['state'] !== $oauthState['value']) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Invalid OAuth state.'));
}

list($tokenCode, $tokenBody) = ar_http_post_form('https://oauth2.googleapis.com/token', array(
    'code' => $_GET['code'],
    'client_id' => ar_google_client_id(),
    'client_secret' => ar_google_client_secret(),
    'redirect_uri' => ar_google_redirect_uri(),
    'grant_type' => 'authorization_code'
));

$tokenJson = json_decode($tokenBody, true);
if ($tokenCode < 200 || $tokenCode >= 300 || !isset($tokenJson['access_token'])) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Google token exchange failed.'));
}

list($userCode, $userBody) = ar_http_get_json('https://openidconnect.googleapis.com/v1/userinfo', array(
    'Authorization: Bearer ' . $tokenJson['access_token']
));
$userJson = json_decode($userBody, true);
if ($userCode < 200 || $userCode >= 300 || !isset($userJson['sub'])) {
    ar_redirect(ar_web_root() . '/auth/login.php?error=' . urlencode('Unable to read Google user profile.'));
}

$sub = $userJson['sub'];
$email = isset($userJson['email']) ? $userJson['email'] : '';
$name = isset($userJson['name']) ? $userJson['name'] : $email;
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
