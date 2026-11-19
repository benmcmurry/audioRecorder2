<?php
require_once __DIR__ . '/common.php';

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
if (!in_array($requestMethod, array('GET', 'POST'), true)) {
    ar_lti_error(405, 'Method not allowed.');
}
$loginRequest = $requestMethod === 'POST' ? $_POST : $_GET;
$issuer = trim((string) ($loginRequest['iss'] ?? ''));
$clientId = trim((string) ($loginRequest['client_id'] ?? ''));
$loginHint = trim((string) ($loginRequest['login_hint'] ?? ''));
$targetUri = trim((string) ($loginRequest['target_link_uri'] ?? ''));
$platform = ar_lti_platform_for_issuer($issuer, $clientId);
$config = ar_lti_config();
$target = parse_url($targetUri);
$launchBase = parse_url($config['launch_uri']);
$validLaunchTarget = is_array($target) && is_array($launchBase)
    && strtolower((string) ($target['scheme'] ?? '')) === strtolower((string) ($launchBase['scheme'] ?? ''))
    && strtolower((string) ($target['host'] ?? '')) === strtolower((string) ($launchBase['host'] ?? ''))
    && (string) ($target['path'] ?? '') === (string) ($launchBase['path'] ?? '')
    && empty($target['fragment']);
if ($validLaunchTarget && !empty($target['query'])) {
    parse_str($target['query'], $targetQuery);
    $validLaunchTarget = count($targetQuery) === 1 && preg_match('/\A[a-f0-9]{64}\z/', (string) ($targetQuery['resource_key'] ?? ''));
}
if (!$platform || $loginHint === '' || (!$validLaunchTarget && $targetUri !== $config['deep_link_uri'])) {
    ar_lti_error(400, 'This Canvas launch request is not configured.');
}

// Rotate before the cross-site OIDC form_post. Rotating after Canvas posts the
// validated launch can lose the new cookie in Safari's embedded context before
// the redirected content picker gets its next request.
session_regenerate_id(true);

$state = bin2hex(random_bytes(32));
$nonce = bin2hex(random_bytes(32));
if (!isset($_SESSION['ar_lti_oidc']) || !is_array($_SESSION['ar_lti_oidc'])) {
    $_SESSION['ar_lti_oidc'] = array();
}
foreach ($_SESSION['ar_lti_oidc'] as $existingState => $transaction) {
    if (!is_array($transaction) || (int) ($transaction['created_at'] ?? 0) < time() - 600) {
        unset($_SESSION['ar_lti_oidc'][$existingState]);
    }
}
$_SESSION['ar_lti_oidc'][$state] = array(
    'nonce' => $nonce,
    'issuer' => rtrim($issuer, '/'),
    'client_id' => $clientId,
    'target_link_uri' => $targetUri,
    'created_at' => time(),
);

$params = array(
    'scope' => 'openid',
    'response_type' => 'id_token',
    'response_mode' => 'form_post',
    'prompt' => 'none',
    'client_id' => $clientId,
    'redirect_uri' => $config['launch_uri'],
    'login_hint' => $loginHint,
    'state' => $state,
    'nonce' => $nonce,
);
if (isset($loginRequest['lti_message_hint']) && (string) $loginRequest['lti_message_hint'] !== '') {
    $params['lti_message_hint'] = (string) $loginRequest['lti_message_hint'];
}
if (isset($loginRequest['lti_deployment_id'])) {
    $params['lti_deployment_id'] = (string) $loginRequest['lti_deployment_id'];
}
$separator = strpos((string) $platform['auth_login_url'], '?') === false ? '?' : '&';
header('Location: ' . $platform['auth_login_url'] . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986), true, 302);
exit;
