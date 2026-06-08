<?php
function ar_request_scheme() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $values = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
        return strtolower(trim($values[0])) === 'https' ? 'https' : 'http';
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTOCOL'])) {
        $values = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTOCOL']);
        return strtolower(trim($values[0])) === 'https' ? 'https' : 'http';
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return 'https';
    }

    if (!empty($_SERVER['HTTPS']) && strcasecmp((string) $_SERVER['HTTPS'], 'off') !== 0) {
        return 'https';
    }

    return 'http';
}

function ar_request_host() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
        $host = trim($hosts[0]);
        if ($host !== '') {
            return $host;
        }
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
        return $_SERVER['HTTP_HOST'];
    }

    if (!empty($_SERVER['SERVER_NAME'])) {
        return $_SERVER['SERVER_NAME'];
    }

    return 'localhost';
}

function ar_request_origin() {
    return ar_request_scheme() . '://' . ar_request_host();
}

function ar_public_origin() {
    $envOrigin = getenv('AR_PUBLIC_ORIGIN');
    if ($envOrigin) {
        return rtrim(trim($envOrigin), '/');
    }

    return ar_request_origin();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => ar_request_scheme() === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

function ar_server_config() {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = array();
    $configPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/google_auth_config.php';
    if (is_readable($configPath)) {
        $loaded = include $configPath;
        if (is_array($loaded)) {
            $config = $loaded;
        }
    }

    return $config;
}

function ar_web_root() {
    $envRoot = getenv('AR_WEB_ROOT');
    if ($envRoot) {
        return '/' . trim($envRoot, '/');
    }

    $appFolder = basename(realpath(__DIR__ . '/..'));
    $requestPath = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    if ($requestPath && preg_match('#^(.*?/' . preg_quote($appFolder, '#') . ')(?:/|$)#', $requestPath, $m)) {
        return rtrim($m[1], '/');
    }

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    if ($scriptName && preg_match('#^(.*?/' . preg_quote($appFolder, '#') . ')(?:/|$)#', $scriptName, $m)) {
        return rtrim($m[1], '/');
    }

    return '/' . $appFolder;
}

function ar_current_url() {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    return ar_public_origin() . $uri;
}

function ar_auth_debug_enabled() {
    $env = getenv('AR_AUTH_DEBUG');
    return $env !== false && $env !== '' && $env !== '0' && strtolower((string) $env) !== 'false';
}

function ar_auth_debug_log($stage, $extra = array()) {
    if (!ar_auth_debug_enabled()) {
        return;
    }

    $payload = array(
        'stage' => $stage,
        'time' => gmdate('c'),
        'host' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
        'xfh' => isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : '',
        'xfp' => isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : '',
        'scheme' => ar_request_scheme(),
        'uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
        'script' => isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '',
        'session_id' => function_exists('session_id') ? session_id() : '',
    );

    foreach ($extra as $key => $value) {
        $payload[$key] = $value;
    }

    error_log('[audioRecorder-auth] ' . json_encode($payload));
}

function ar_redirect($path) {
    header('Location: ' . $path);
    exit;
}

function ar_build_url_with_query($url, $params) {
    $parts = parse_url($url);
    $query = array();
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = isset($parts['host']) ? $parts['host'] : '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $user = isset($parts['user']) ? $parts['user'] : '';
    $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
    $pass = ($user !== '' || $pass !== '') ? $pass . '@' : '';
    $path = isset($parts['path']) ? $parts['path'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    $queryString = $query ? '?' . http_build_query($query, '', '&') : '';

    return $scheme . $user . $pass . $host . $port . $path . $queryString . $fragment;
}

function ar_safe_redirect_target($target, $fallback) {
    if (!$target) {
        return $fallback;
    }

    if (preg_match('/^https?:\/\//i', $target)) {
        $parts = parse_url($target);
        $currentHost = parse_url(ar_public_origin(), PHP_URL_HOST);
        if ($parts && isset($parts['host']) && $currentHost && strcasecmp($parts['host'], $currentHost) === 0) {
            return $target;
        }
        return $fallback;
    }

    if (strpos($target, '/') === 0) {
        return $target;
    }

    return $fallback;
}

function ar_random_string($length) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes((int) ceil($length / 2)));
    }
    return substr(sha1(uniqid(mt_rand(), true)), 0, $length);
}

function ar_include_db() {
    global $elc_db;

    if (!isset($elc_db) || !($elc_db instanceof mysqli)) {
        include_once __DIR__ . '/../../../connectFiles/connect_ar.php';
    }

    return $elc_db;
}

function ar_ensure_auth_tables() {
    $db = ar_include_db();
    $sql = "CREATE TABLE IF NOT EXISTS User_auth_providers (
        auth_id INT NOT NULL AUTO_INCREMENT,
        provider VARCHAR(32) NOT NULL,
        provider_user_id VARCHAR(255) NOT NULL,
        netid VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (auth_id),
        UNIQUE KEY uq_provider_subject (provider, provider_user_id),
        KEY idx_netid (netid)
    )";
    $db->query($sql);
}

function ar_google_client_id() {
    $env = getenv('GOOGLE_SHARED_CLIENT_ID');
    return $env ? $env : '';
}

function ar_google_client_secret() {
    $env = getenv('GOOGLE_SHARED_CLIENT_SECRET');
    return $env ? $env : '';
}

function ar_google_redirect_uri() {
    $config = ar_server_config();
    if (isset($config['google_shared_redirect_uri']) && $config['google_shared_redirect_uri'] !== '') {
        return trim($config['google_shared_redirect_uri']);
    }
    $env = getenv('GOOGLE_SHARED_REDIRECT_URI');
    if ($env) {
        return trim($env);
    }
    return rtrim(ar_google_shared_root(), '/') . '/google_callback.php';
}

function ar_http_post_form($url, $fields) {
    $body = http_build_query($fields, '', '&');

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($code, $response);
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'ignore_errors' => true
        )
    ));
    $response = file_get_contents($url, false, $context);
    $code = 200;
    $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : array();
    if (isset($headers[0])) {
        if (preg_match('/\s(\d{3})\s/', $headers[0], $m)) {
            $code = (int) $m[1];
        }
    }
    return array($code, $response);
}

function ar_http_get_json($url, $headers) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($code, $response);
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true
        )
    ));
    $response = file_get_contents($url, false, $context);
    $code = 200;
    $responseHeaders = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : array();
    if (isset($responseHeaders[0])) {
        if (preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
            $code = (int) $m[1];
        }
    }
    return array($code, $response);
}

function ar_upsert_user($netid, $name) {
    $db = ar_include_db();
    if ($name === '') {
        $name = 'User';
    }
    $query = $db->prepare("INSERT INTO Users (netid, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
    $query->bind_param('ss', $netid, $name);
    $query->execute();
}

function ar_find_google_link($sub) {
    ar_ensure_auth_tables();
    $db = ar_include_db();
    $provider = 'google';
    $query = $db->prepare("SELECT netid FROM User_auth_providers WHERE provider = ? AND provider_user_id = ? LIMIT 1");
    $query->bind_param('ss', $provider, $sub);
    $query->execute();
    $result = $query->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? $row['netid'] : null;
}

function ar_set_google_link($sub, $netid, $email) {
    ar_ensure_auth_tables();
    $db = ar_include_db();
    $provider = 'google';
    $query = $db->prepare("INSERT INTO User_auth_providers (provider, provider_user_id, netid, email) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE netid = VALUES(netid), email = VALUES(email)");
    $query->bind_param('ssss', $provider, $sub, $netid, $email);
    $query->execute();
}

function ar_google_subject_to_netid($sub) {
    $clean = preg_replace('/[^A-Za-z0-9._-]/', '', $sub);
    if ($clean === '') {
        $clean = md5($sub);
    }
    return 'google_' . $clean;
}

function ar_migrate_netid_data($oldNetid, $newNetid) {
    if ($oldNetid === '' || $newNetid === '' || $oldNetid === $newNetid) {
        return;
    }

    $db = ar_include_db();

    $query = $db->prepare("UPDATE Audio_files SET netid = ? WHERE netid = ?");
    $query->bind_param('ss', $newNetid, $oldNetid);
    $query->execute();

    $query = $db->prepare("UPDATE Prompts SET netid = ? WHERE netid = ?");
    $query->bind_param('ss', $newNetid, $oldNetid);
    $query->execute();

    $query = $db->prepare("SELECT name FROM Users WHERE netid = ? LIMIT 1");
    $query->bind_param('s', $newNetid);
    $query->execute();
    $result = $query->get_result();
    $newUser = $result ? $result->fetch_assoc() : null;

    if (!$newUser) {
        $query = $db->prepare("UPDATE Users SET netid = ? WHERE netid = ?");
        $query->bind_param('ss', $newNetid, $oldNetid);
        $query->execute();
    }
}

function ar_set_session_user($provider, $netid, $name, $email) {
    $_SESSION['auth_user'] = array(
        'provider' => $provider,
        'netid' => $netid,
        'name' => $name,
        'email' => $email
    );
}

function ar_get_session_user() {
    if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
        return $_SESSION['auth_user'];
    }
    return null;
}

function ar_clear_session_user() {
    unset($_SESSION['auth_user']);
    unset($_SESSION['oauth_state']);
    unset($_SESSION['google_link_target_netid']);
    unset($_SESSION['ar_google_login']);
}

function ar_auth_required_redirect() {
    $target = urlencode(ar_current_url());
    ar_auth_debug_log('auth_required_redirect', array('target' => $target));
    ar_redirect(ar_web_root() . '/auth/login.php?redirect=' . $target);
}

function ar_google_app_id() {
    $env = getenv('AR_GOOGLE_APP_ID');
    if ($env) {
        return trim($env);
    }

    $config = ar_server_config();
    if (isset($config['shared_auth_apps']) && is_array($config['shared_auth_apps'])) {
        $appFolder = basename(realpath(__DIR__ . '/..'));
        if (isset($config['shared_auth_apps'][$appFolder])) {
            return $config['shared_auth_apps'][$appFolder];
        }
    }

    return basename(realpath(__DIR__ . '/..'));
}

function ar_google_shared_root() {
    $env = getenv('SHARED_AUTH_WEB_ROOT');
    if ($env) {
        return rtrim(trim($env), '/');
    }

    $config = ar_server_config();
    if (isset($config['shared_auth_web_root']) && $config['shared_auth_web_root'] !== '') {
        return rtrim(trim($config['shared_auth_web_root']), '/');
    }

    return ar_public_origin() . '/sharedAuth';
}

function ar_google_expected_issuer() {
    $env = getenv('SHARED_AUTH_ISSUER');
    if ($env) {
        return rtrim(trim($env), '/');
    }

    $config = ar_server_config();
    if (isset($config['shared_auth_issuer']) && $config['shared_auth_issuer'] !== '') {
        return rtrim(trim($config['shared_auth_issuer']), '/');
    }

    return rtrim(ar_public_origin() . '/sharedAuth', '/');
}

function ar_google_consume_url() {
    return ar_public_origin() . ar_web_root() . '/auth/google_callback.php';
}

function ar_google_public_key_path() {
    $env = getenv('GOOGLE_SHARED_PUBLIC_KEY_PATH');
    if ($env) {
        return trim($env);
    }

    $config = ar_server_config();
    if (isset($config['google_shared_public_key_path']) && $config['google_shared_public_key_path'] !== '') {
        return trim($config['google_shared_public_key_path']);
    }

    return dirname($_SERVER['DOCUMENT_ROOT']) . '/keys/google_jwt_public.pem';
}

function ar_google_shared_enabled() {
    return is_readable(ar_google_public_key_path());
}

function ar_base64url_decode($input) {
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $input .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($input, '-_', '+/'));
}

function ar_verify_google_token($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    $headerJson = ar_base64url_decode($parts[0]);
    $payloadJson = ar_base64url_decode($parts[1]);
    $signature = ar_base64url_decode($parts[2]);
    if ($headerJson === false || $payloadJson === false || $signature === false) {
        return null;
    }

    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload) || !isset($header['alg']) || $header['alg'] !== 'RS256') {
        return null;
    }

    $publicKeyPath = ar_google_public_key_path();
    if (!is_readable($publicKeyPath)) {
        return null;
    }

    $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
    if (!$publicKey) {
        return null;
    }

    $verified = openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if ($verified !== 1) {
        return null;
    }

    $now = time();
    if (!isset($payload['exp']) || (int) $payload['exp'] < $now) {
        return null;
    }

    if (!isset($payload['aud']) || (string) $payload['aud'] !== ar_google_app_id()) {
        return null;
    }

    if (!isset($payload['iss']) || rtrim((string) $payload['iss'], '/') !== ar_google_expected_issuer()) {
        return null;
    }

    return $payload;
}
