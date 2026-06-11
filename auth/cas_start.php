<?php
include_once __DIR__ . '/common.php';

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);

ar_redirect(shared_auth_login_url($redirect, 'audioRecorder'));
