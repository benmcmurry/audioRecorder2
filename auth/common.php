<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    return $scheme . '://' . $host . $uri;
}

function ar_redirect($path) {
    header('Location: ' . $path);
    exit;
}

function ar_safe_redirect_target($target, $fallback) {
    if (!$target) {
        return $fallback;
    }

    if (preg_match('/^https?:\/\//i', $target)) {
        $parts = parse_url($target);
        $currentHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if ($parts && isset($parts['host']) && strcasecmp($parts['host'], $currentHost) === 0) {
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
    $env = getenv('GOOGLE_CLIENT_ID');
    return $env ? $env : '';
}

function ar_google_client_secret() {
    $env = getenv('GOOGLE_CLIENT_SECRET');
    return $env ? $env : '';
}

function ar_google_redirect_uri() {
    $override = getenv('GOOGLE_REDIRECT_URI');
    if ($override) {
        return $override;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $scheme . '://' . $host . ar_web_root() . '/auth/google_callback.php';
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
}

function ar_auth_required_redirect() {
    $target = urlencode(ar_current_url());
    ar_redirect(ar_web_root() . '/auth/login.php?redirect=' . $target);
}
