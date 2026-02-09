<?php
include_once __DIR__ . '/common.php';

$provider = '';
if (isset($_SESSION['auth_user']) && isset($_SESSION['auth_user']['provider'])) {
    $provider = $_SESSION['auth_user']['provider'];
}

ar_clear_session_user();

if ($provider === 'cas') {
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/config.php';
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/CAS.php';
    phpCAS::setVerbose(true);
    phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
    phpCAS::setNoCasServerValidation();
    phpCAS::logout();
    exit;
}

ar_redirect(ar_web_root() . '/auth/login.php');
