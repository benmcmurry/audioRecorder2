<?php
include_once __DIR__ . '/common.php';
ar_auth_debug_log('logout.php');

$returnTo = shared_auth_build_url_with_query(ar_web_root() . '/login.php', array(
    'redirect' => ar_web_root() . '/index.php',
));

ar_clear_local_session();
shared_auth_redirect(shared_auth_logout_url($returnTo, 'audioRecorder'));
