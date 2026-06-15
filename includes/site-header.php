<?php
require_once dirname(__DIR__, 2) . '/shared-ui/layout.php';

$appRoot = function_exists('ar_web_root') ? ar_web_root() : '';
$sessionUser = function_exists('ar_get_session_user') ? ar_get_session_user() : null;
$sharedUser = (!$sessionUser && function_exists('shared_auth_current_session_user'))
    ? shared_auth_current_session_user()
    : null;
$currentUser = $sessionUser ?: $sharedUser;
$displayName = '';

if ($currentUser && isset($currentUser['name']) && trim((string) $currentUser['name']) !== '') {
    $displayName = trim((string) $currentUser['name']);
} elseif ($currentUser && isset($currentUser['netid']) && trim((string) $currentUser['netid']) !== '') {
    $displayName = trim((string) $currentUser['netid']);
}

$currentUrl = function_exists('ar_current_url') ? ar_current_url() : ($appRoot . '/index.php');
$loginUrl = shared_auth_login_url($currentUrl, 'audioRecorder');
$logoutUrl = shared_auth_logout_url($currentUrl, 'audioRecorder');

$menuItems = array(
    array('label' => 'Home', 'href' => $appRoot . '/index.php'),
    array('label' => 'Teacher Area', 'href' => $appRoot . '/teacher/index.php')
);

if ($currentUser) {
    $menuItems[] = array('label' => 'Profile', 'href' => $appRoot . '/profile.php');
}

shared_ui_render_header(array(
    'brand_href' => $appRoot . '/index.php',
    'brand_label' => 'ELC Audio Recorder',
    'brand_image' => shared_ui_asset_url('assets/img/elc.png'),
    'brand_image_alt' => 'ELC Audio Recorder',
    'brand_title' => 'Audio Recorder',
    'nav_items' => array(),
    'user' => $currentUser,
    'display_name' => $displayName,
    'auth_href' => $loginUrl,
    'logout_href' => $logoutUrl,
    'menu_items' => $menuItems,
    'sign_in_label' => 'Sign In',
    'sign_out_label' => 'Logout'
));
