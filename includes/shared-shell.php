<?php
require_once dirname(__DIR__, 2) . '/shared-ui/layout.php';

if (!function_exists('audio_recorder_shell_context')) {
    function audio_recorder_shell_context() {
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

        return array(
            'app_root' => $appRoot,
            'current_user' => $currentUser,
            'display_name' => $displayName,
            'login_url' => shared_auth_login_url($currentUrl, 'audioRecorder'),
            'logout_url' => shared_auth_logout_url($currentUrl, 'audioRecorder'),
        );
    }
}

if (!function_exists('audio_recorder_render_header')) {
    function audio_recorder_render_header() {
        $context = audio_recorder_shell_context();
        $menuItems = array(
            array('label' => 'Home', 'href' => $context['app_root'] . '/index.php'),
            array('label' => 'Teacher Area', 'href' => $context['app_root'] . '/teacher/index.php'),
        );

        if ($context['current_user']) {
            $menuItems[] = array('label' => 'Profile', 'href' => $context['app_root'] . '/profile.php');
        }

        shared_ui_render_header(array(
            'brand_href' => $context['app_root'] . '/index.php',
            'brand_label' => 'ELC Audio Recorder',
            'brand_image' => shared_ui_asset_url('assets/img/elc.png'),
            'brand_image_alt' => 'ELC Audio Recorder',
            'brand_title' => 'Audio Recorder',
            'nav_items' => array(),
            'user' => $context['current_user'],
            'display_name' => $context['display_name'],
            'auth_href' => $context['login_url'],
            'logout_href' => $context['logout_url'],
            'menu_items' => $menuItems,
            'sign_in_label' => 'Sign In',
            'sign_out_label' => 'Logout',
        ));
    }
}

if (!function_exists('audio_recorder_render_footer')) {
    function audio_recorder_render_footer() {
        $context = audio_recorder_shell_context();
        $signInOutUrl = $context['current_user'] ? $context['logout_url'] : $context['login_url'];

        shared_ui_render_footer(array(
            'columns' => array(
                array(
                    'title' => 'Audio Recorder',
                    'items' => array(
                        array('label' => 'Home', 'href' => $context['app_root'] . '/index.php'),
                        array('label' => 'Teacher Area', 'href' => $context['app_root'] . '/teacher/index.php'),
                    ),
                ),
                array(
                    'title' => 'Support',
                    'items' => array(
                        array('label' => 'Profile', 'href' => $context['app_root'] . '/profile.php'),
                        array('label' => 'Sign In / Out', 'href' => $signInOutUrl),
                    ),
                ),
                array(
                    'title' => 'English Language Center',
                    'items' => array(
                        array('label' => 'English Language Center', 'href' => 'https://elc.byu.edu'),
                        array('label' => 'BYU', 'href' => 'https://www.byu.edu'),
                    ),
                ),
            ),
            'note' => 'Developed by Ben McMurry',
        ));
    }
}
