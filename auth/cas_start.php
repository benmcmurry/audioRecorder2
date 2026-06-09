<?php
include_once __DIR__ . '/common.php';
ar_auth_debug_log('cas_start.php');

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/Web/sharedAuth/broker.php';

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);

$authState = shared_auth_cas_require_authentication(ar_public_origin() . ar_web_root() . '/auth/cas_start.php?redirect=' . urlencode($redirect));
$identity = $authState['identity'];
$netid = isset($identity['netid']) ? $identity['netid'] : '';
$name = isset($identity['name']) ? $identity['name'] : $netid;
$email = isset($identity['emailAddress']) ? $identity['emailAddress'] : '';

ar_upsert_user($netid, $name);
ar_set_session_user('cas', $netid, $name, $email);

ar_redirect($redirect);
