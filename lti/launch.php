<?php
require_once __DIR__ . '/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ar_lti_error(405, 'Canvas launches must use a signed form_post request.');
}
$state = (string) ($_POST['state'] ?? '');
$token = (string) ($_POST['id_token'] ?? '');
if (!preg_match('/\A[a-f0-9]{64}\z/', $state) || $token === '' || strlen($token) > 50000) {
    ar_lti_error(400, 'The Canvas launch is incomplete.');
}
$transaction = $_SESSION['ar_lti_oidc'][$state] ?? null;
unset($_SESSION['ar_lti_oidc'][$state]);
if (!is_array($transaction) || (int) ($transaction['created_at'] ?? 0) < time() - 600) {
    ar_lti_error(403, 'The Canvas launch has expired. Start again from Canvas.');
}
try {
    $unverifiedParts = explode('.', $token);
    if (count($unverifiedParts) !== 3) {
        throw new RuntimeException('Malformed Canvas launch token.');
    }
    $unverifiedClaims = ar_lti_jwt_decode_json($unverifiedParts[1]);
    $platform = ar_lti_platform_for_issuer((string) ($unverifiedClaims['iss'] ?? ''), (string) $transaction['client_id']);
    if (!$platform || rtrim((string) $transaction['issuer'], '/') !== rtrim((string) $platform['issuer'], '/')) {
        throw new RuntimeException('Canvas issuer did not match the login request.');
    }
    $claims = ar_lti_verify_id_token($token, $platform, (string) $transaction['nonce']);
    $deployment = (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? '');
    $messageType = (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/message_type'] ?? '');
    $targetUri = (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/target_link_uri'] ?? $transaction['target_link_uri']);
    if (!hash_equals((string) $transaction['target_link_uri'], $targetUri)
        || !in_array($messageType, array('LtiResourceLinkRequest', 'LtiDeepLinkingRequest'), true)) {
        throw new RuntimeException('Canvas launch type or target did not match the login request.');
    }
    if ($messageType === 'LtiResourceLinkRequest' && empty($claims['https://purl.imsglobal.org/spec/lti/claim/resource_link']['id'])) {
        throw new RuntimeException('Canvas assignment launch did not include a resource link.');
    }
    if ($messageType === 'LtiDeepLinkingRequest' && empty($claims['https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings']['deep_link_return_url'])) {
        throw new RuntimeException('Canvas content-selection launch did not include a return URL.');
    }
    session_regenerate_id(true);
    ar_lti_set_user($platform, $claims);
    ar_lti_store_launch_context($platform, $claims, (string) $transaction['target_link_uri']);
    $_SESSION['ar_lti_platform'] = array(
        'issuer' => rtrim((string) $platform['issuer'], '/'),
        'client_id' => (string) $platform['client_id'],
        'deployment_id' => $deployment,
    );
    if ($messageType === 'LtiDeepLinkingRequest') {
        header('Location: ' . ar_lti_base_url('lti/content-picker.php'));
        exit;
    }
    $resourceLink = (string) ($_SESSION['ar_lti_launch']['target_link_uri'] ?? '');
    $resourceKey = '';
    $parts = parse_url($resourceLink);
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        $resourceKey = (string) ($query['resource_key'] ?? '');
    }
    if (!preg_match('/\A[a-f0-9]{64}\z/', $resourceKey)) {
        ar_lti_html_start('Canvas launch');
        echo '<div class="dashboard-card"><h1>Assignment link needs setup</h1><p>This Canvas assignment does not include a recognized Audio Recorder resource. Ask the teacher to select the prompt or activity again.</p></div>';
        ar_lti_html_end();
        exit;
    }
    header('Location: ' . ar_lti_base_url('lti/resource.php?resource_key=' . rawurlencode($resourceKey)));
    exit;
} catch (Throwable $error) {
    error_log('[audioRecorder-lti] launch rejected: ' . $error->getMessage());
    ar_lti_error(403, 'Canvas launch could not be validated. Start again from Canvas or contact the Audio Recorder administrator.');
}
