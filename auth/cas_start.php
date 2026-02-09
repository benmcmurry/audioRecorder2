<?php
include_once __DIR__ . '/common.php';

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/config.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/CAS.php';

phpCAS::setVerbose(true);
phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
phpCAS::setNoCasServerValidation();
phpCAS::forceAuthentication();

$netid = phpCAS::getUser();
$attrs = phpCAS::getAttributes();
$name = isset($attrs['name']) ? $attrs['name'] : $netid;
$email = isset($attrs['email']) ? $attrs['email'] : '';

ar_upsert_user($netid, $name);
ar_set_session_user('cas', $netid, $name, $email);

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);
ar_redirect($redirect);
