<?php
require_once __DIR__ . '/../auth/common.php';
require_once __DIR__ . '/crypto.php';

function ar_lti_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $raw = getenv('AR_LTI_PLATFORMS_JSON');
    $platforms = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($platforms)) {
        $platforms = array();
    }

    $privateKeyPath = trim((string) getenv('AR_LTI_PRIVATE_KEY_PATH'));
    $privateKey = $privateKeyPath !== '' && is_readable($privateKeyPath) ? file_get_contents($privateKeyPath) : false;
    $appRoot = function_exists('ar_web_root') ? ar_web_root() : '/audioRecorder';

    $config = array(
        'platforms' => array_values(array_filter($platforms, 'is_array')),
        'private_key' => is_string($privateKey) ? $privateKey : '',
        'key_id' => trim((string) (getenv('AR_LTI_KEY_ID') ?: '')),
        'public_origin' => function_exists('ar_public_origin') ? ar_public_origin() : '',
        'app_root' => $appRoot,
        'login_uri' => rtrim(function_exists('ar_public_origin') ? ar_public_origin() : '', '/') . $appRoot . '/lti/login.php',
        'launch_uri' => rtrim(function_exists('ar_public_origin') ? ar_public_origin() : '', '/') . $appRoot . '/lti/launch.php',
        'deep_link_uri' => rtrim(function_exists('ar_public_origin') ? ar_public_origin() : '', '/') . $appRoot . '/lti/deep-link.php',
        'jwks_uri' => rtrim(function_exists('ar_public_origin') ? ar_public_origin() : '', '/') . $appRoot . '/lti/jwks.php',
    );
    return $config;
}

function ar_lti_db(): mysqli
{
    static $db = null;
    if ($db instanceof mysqli) {
        return $db;
    }
    $privateRoot = getenv('APP_PRIVATE_ROOT');
    $connectPath = ($privateRoot ? rtrim(trim((string) $privateRoot), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php';
    if (!is_readable($connectPath)) {
        throw new RuntimeException('Audio Recorder database configuration is unavailable.');
    }
    require $connectPath;
    if (!isset($elc_db) || !$elc_db instanceof mysqli) {
        throw new RuntimeException('Audio Recorder database connection is unavailable.');
    }
    $db = $elc_db;
    return $db;
}

function ar_lti_platform_for_issuer(string $issuer, ?string $clientId = null): ?array
{
    foreach (ar_lti_config()['platforms'] as $platform) {
        $configuredIssuer = isset($platform['issuer']) ? rtrim((string) $platform['issuer'], '/') : '';
        $configuredClient = isset($platform['client_id']) ? (string) $platform['client_id'] : '';
        if (strpos($configuredIssuer, 'https://') !== 0 || !parse_url($configuredIssuer, PHP_URL_HOST)
            || $configuredIssuer !== rtrim($issuer, '/') || ($clientId !== null && $configuredClient !== $clientId)) {
            continue;
        }
        foreach (array('issuer', 'client_id', 'auth_login_url', 'token_url', 'jwks_url', 'deployment_ids') as $required) {
            if (!isset($platform[$required])) {
                return null;
            }
        }
        foreach (array('auth_login_url', 'token_url', 'jwks_url') as $urlKey) {
            if (strpos((string) $platform[$urlKey], 'https://') !== 0 || !parse_url((string) $platform[$urlKey], PHP_URL_HOST)) {
                return null;
            }
        }
        return $platform;
    }
    return null;
}

function ar_lti_base_url(string $path): string
{
    return rtrim(ar_lti_config()['public_origin'], '/') . ar_lti_config()['app_root'] . '/' . ltrim($path, '/');
}

function ar_lti_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ar_lti_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

function ar_lti_html_start(string $title): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeTitle . ' | ELC Audio Recorder</title>';
    include __DIR__ . '/../includes/styles_and_scripts.php';
    echo '</head><body><main class="container dashboard-shell mt-4 mb-5 pb-3">';
}

function ar_lti_html_end(): void
{
    echo '</main></body></html>';
}

function ar_lti_launch_user(): ?array
{
    $user = $_SESSION['auth_user'] ?? null;
    return is_array($user) && ($user['provider'] ?? '') === 'lti' ? $user : null;
}

function ar_lti_identity_netid(array $platform, array $claims): string
{
    $issuer = (string) ($claims['iss'] ?? '');
    $deploymentId = (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? '');
    $subject = (string) ($claims['sub'] ?? '');
    if ($issuer === '' || $deploymentId === '' || $subject === '') {
        throw new RuntimeException('The Canvas identity is incomplete.');
    }

    $db = ar_lti_db();
    $identityHash = hash('sha256', $issuer . "\0" . $deploymentId . "\0" . $subject);
    $query = $db->prepare('SELECT netid FROM Lti_identities WHERE identity_hash = ? LIMIT 1');
    if (!$query) {
        throw new RuntimeException('Canvas identity storage is not initialized. Apply the LTI database migration.');
    }
    $query->bind_param('s', $identityHash);
    $query->execute();
    $result = $query->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($row && !empty($row['netid'])) {
        return (string) $row['netid'];
    }

    $netid = 'lti_' . substr(hash('sha256', $issuer . "\0" . $deploymentId . "\0" . $subject), 0, 48);
    $name = '';
    if (!empty($claims['name'])) {
        $name = mb_substr(trim((string) $claims['name']), 0, 200);
    } elseif (!empty($claims['given_name']) || !empty($claims['family_name'])) {
        $name = trim((string) ($claims['given_name'] ?? '') . ' ' . (string) ($claims['family_name'] ?? ''));
    }
    if ($name === '') {
        $name = 'Canvas User';
    }
    $insert = $db->prepare('INSERT INTO Lti_identities (issuer, deployment_id, subject, netid, identity_hash, display_name, created_at, last_seen_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_seen_at = NOW()');
    if (!$insert) {
        throw new RuntimeException('Canvas identity storage is not initialized. Apply the LTI database migration.');
    }
    $insert->bind_param('ssssss', $issuer, $deploymentId, $subject, $netid, $identityHash, $name);
    if (!$insert->execute()) {
        throw new RuntimeException('Could not save the Canvas identity.');
    }
    $userQuery = $db->prepare('INSERT INTO Users (netid, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
    if ($userQuery) {
        $userQuery->bind_param('ss', $netid, $name);
        $userQuery->execute();
    }
    return $netid;
}

function ar_lti_set_user(array $platform, array $claims): string
{
    $netid = ar_lti_identity_netid($platform, $claims);
    $_SESSION['ar_lti_identity_key'] = array(
        'issuer' => rtrim((string) ($claims['iss'] ?? ''), '/'),
        'deployment_id' => (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? ''),
        'subject' => (string) ($claims['sub'] ?? ''),
        'nonce' => (string) ($claims['nonce'] ?? ''),
        'netid' => $netid,
    );
    $name = trim((string) ($claims['name'] ?? 'Canvas User'));
    if ($name === '') {
        $name = 'Canvas User';
    }
    $_SESSION['auth_user'] = array(
        'provider' => 'lti',
        'netid' => $netid,
        'name' => mb_substr($name, 0, 200),
        'email' => '',
    );
    return $netid;
}

function ar_lti_store_launch_context(array $platform, array $claims, string $targetLinkUri = ''): void
{
    $roles = $claims['https://purl.imsglobal.org/spec/lti/claim/roles'] ?? array();
    if (!is_array($roles)) {
        $roles = array();
    }
    $context = $claims['https://purl.imsglobal.org/spec/lti/claim/context'] ?? array();
    $resource = $claims['https://purl.imsglobal.org/spec/lti/claim/resource_link'] ?? array();
    $ags = $claims['https://purl.imsglobal.org/spec/lti-ags/claim/endpoint'] ?? array();
    $dl = $claims['https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings'] ?? array();
    $_SESSION['ar_lti_launch'] = array(
        'issuer' => (string) ($claims['iss'] ?? ''),
        'client_id' => (string) $platform['client_id'],
        'deployment_id' => (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? ''),
        'subject' => (string) ($claims['sub'] ?? ''),
        'nonce' => (string) ($claims['nonce'] ?? ''),
        'roles' => $roles,
        'context_id' => is_array($context) ? (string) ($context['id'] ?? '') : '',
        'context_title' => is_array($context) ? (string) ($context['title'] ?? '') : '',
        'resource_link_id' => is_array($resource) ? (string) ($resource['id'] ?? '') : '',
        'target_link_uri' => $targetLinkUri !== '' ? $targetLinkUri : (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/target_link_uri'] ?? ''),
        'ags_lineitem' => is_array($ags) ? (string) ($ags['lineitem'] ?? '') : '',
        'ags_lineitems' => is_array($ags) ? (string) ($ags['lineitems'] ?? '') : '',
        'ags_scopes' => is_array($ags) && isset($ags['scope']) && is_array($ags['scope']) ? $ags['scope'] : array(),
        'deep_link_settings' => is_array($dl) ? $dl : array(),
        'launched_at' => time(),
    );
}

function ar_lti_has_teacher_role(array $launch): bool
{
    foreach (($launch['roles'] ?? array()) as $role) {
        if (in_array((string) $role, array(
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor',
            'http://purl.imsglobal.org/vocab/lis/v2/membership#ContentDeveloper',
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Administrator',
            'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator',
            'http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator',
        ), true)) {
            return true;
        }
    }
    return false;
}

/** Validate that the current Canvas assignment is authorized to submit this prompt/step. */
function ar_lti_validate_upload_context(string $promptId, string $activityId, string $activityItemId, string $netid): bool
{
    $launch = $_SESSION['ar_lti_launch'] ?? null;
    $user = ar_lti_launch_user();
    if (!$user) {
        return true;
    }
    if (!is_array($launch)) {
        return false;
    }
    if ((string) $user['netid'] !== $netid) {
        return false;
    }
    $parts = parse_url((string) ($launch['target_link_uri'] ?? ''));
    $query = array();
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $resourceKey = (string) ($query['resource_key'] ?? '');
    if (!preg_match('/\A[a-f0-9]{64}\z/', $resourceKey)) {
        return false;
    }
    $db = ar_lti_db();
    $keyHash = hash('sha256', $resourceKey);
    $resourceQuery = $db->prepare('SELECT owner_netid, target_type, target_id FROM Lti_resources WHERE resource_key_hash = ? AND issuer = ? AND deployment_id = ? LIMIT 1');
    if (!$resourceQuery) {
        return false;
    }
    $issuer = (string) $launch['issuer'];
    $deployment = (string) $launch['deployment_id'];
    $resourceQuery->bind_param('sss', $keyHash, $issuer, $deployment);
    $resourceQuery->execute();
    $resource = $resourceQuery->get_result()->fetch_assoc();
    if (!$resource) {
        return false;
    }
    if ($activityId === '') {
        return ($activityItemId === '' || $activityItemId === '0')
            && $resource['target_type'] === 'prompt'
            && (string) $resource['target_id'] === $promptId;
    }
    if ($resource['target_type'] !== 'activity' || (string) $resource['target_id'] !== $activityId
        || !preg_match('/\A[0-9]+\z/', $activityItemId)) {
        return false;
    }
    $item = $db->prepare('SELECT Activity_items.item_id FROM Activity_items JOIN Activities ON Activities.activity_id = Activity_items.activity_id WHERE Activity_items.item_id = ? AND Activity_items.activity_id = ? AND Activity_items.prompt_id = ? AND Activities.netid = ? AND Activities.archive = 0 LIMIT 1');
    if (!$item) {
        return false;
    }
    $item->bind_param('isss', $activityItemId, $activityId, $promptId, $resource['owner_netid']);
    $item->execute();
    return (bool) $item->get_result()->fetch_assoc();
}

