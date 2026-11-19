<?php
require_once __DIR__ . '/common.php';
header('Cache-Control: public, max-age=300');
try {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('keys' => array(ar_lti_public_jwk())), JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('[audioRecorder-lti] JWKS unavailable: ' . $error->getMessage());
    ar_lti_json(503, array('error' => 'LTI signing is not configured.'));
}
