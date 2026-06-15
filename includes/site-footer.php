<?php
require_once dirname(__DIR__, 2) . '/shared-ui/layout.php';

$appRoot = function_exists('ar_web_root') ? ar_web_root() : '';
$sessionUser = function_exists('ar_get_session_user') ? ar_get_session_user() : null;
$currentUser = $sessionUser ?: (function_exists('shared_auth_current_session_user') ? shared_auth_current_session_user() : null);
$currentUrl = function_exists('ar_current_url') ? ar_current_url() : ($appRoot . '/index.php');
$signInOutUrl = $currentUser
    ? shared_auth_logout_url($currentUrl, 'audioRecorder')
    : shared_auth_login_url($currentUrl, 'audioRecorder');

shared_ui_render_footer(array(
    'columns' => array(
        array(
            'title' => 'Audio Recorder',
            'items' => array(
                array('label' => 'Home', 'href' => $appRoot . '/index.php'),
                array('label' => 'Teacher Area', 'href' => $appRoot . '/teacher/index.php')
            )
        ),
        array(
            'title' => 'Support',
            'items' => array(
                array('label' => 'Profile', 'href' => $appRoot . '/profile.php'),
                array('label' => 'Sign In / Out', 'href' => $signInOutUrl)
            )
        ),
        array(
            'title' => 'English Language Center',
            'items' => array(
                array('label' => 'English Language Center', 'href' => 'https://elc.byu.edu'),
                array('label' => 'BYU', 'href' => 'https://www.byu.edu')
            )
        )
    ),
    'note' => 'Developed by Ben McMurry'
));
