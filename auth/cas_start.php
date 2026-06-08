<?php
include_once __DIR__ . '/common.php';
ar_auth_debug_log('cas_start.php');

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/config.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/CAS.php';

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);

phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
phpCAS::setFixedServiceURL(ar_public_origin() . ar_web_root() . '/auth/cas_start.php?redirect=' . urlencode($redirect));
phpCAS::setNoCasServerValidation();
phpCAS::forceAuthentication();

$netid = phpCAS::getUser();
$attrs = phpCAS::getAttributes();
$name = isset($attrs['name']) ? $attrs['name'] : $netid;
$email = isset($attrs['email']) ? $attrs['email'] : '';

ar_upsert_user($netid, $name);
ar_set_session_user('cas', $netid, $name, $email);

ar_redirect($redirect);
