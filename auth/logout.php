<?php
include_once __DIR__ . '/common.php';
ar_auth_debug_log('logout.php');

$provider = '';
if (isset($_SESSION['auth_user']) && isset($_SESSION['auth_user']['provider'])) {
    $provider = $_SESSION['auth_user']['provider'];
}

ar_clear_session_user();

if ($provider === 'cas') {
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/Web/sharedAuth/broker.php';
    shared_auth_cas_bootstrap();
    phpCAS::logout();
}

ar_redirect(ar_web_root() . '/auth/login.php');
