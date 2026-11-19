<?php
function ar_lti_b64url_decode(string $value): string
{
    if ($value === '' || !preg_match('/\A[A-Za-z0-9_-]+\z/', $value)) {
        throw new RuntimeException('Malformed signed token.');
    }
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    if ($decoded === false) {
        throw new RuntimeException('Malformed signed token.');
    }
    return $decoded;
}

function ar_lti_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function ar_lti_der_length(int $length): string
{
    if ($length < 128) {
        return chr($length);
    }
    $bytes = '';
    while ($length > 0) {
        $bytes = chr($length & 0xff) . $bytes;
        $length >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function ar_lti_der(int $tag, string $value): string
{
    return chr($tag) . ar_lti_der_length(strlen($value)) . $value;
}

function ar_lti_jwk_rsa_pem(array $jwk): string
{
    if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
        throw new RuntimeException('Unsupported Canvas signing key.');
    }
    $n = ar_lti_b64url_decode((string) $jwk['n']);
    $e = ar_lti_b64url_decode((string) $jwk['e']);
    $n = ltrim($n, "\0");
    $e = ltrim($e, "\0");
    if ($n === '' || $e === '') {
        throw new RuntimeException('Invalid Canvas signing key.');
    }
    if ((ord($n[0]) & 0x80) !== 0) {
        $n = "\0" . $n;
    }
    if ((ord($e[0]) & 0x80) !== 0) {
        $e = "\0" . $e;
    }
    $rsa = ar_lti_der(0x30, ar_lti_der(0x02, $n) . ar_lti_der(0x02, $e));
    $algorithm = hex2bin('300d06092a864886f70d0101010500');
    $spki = ar_lti_der(0x30, $algorithm . ar_lti_der(0x03, "\0" . $rsa));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function ar_lti_jwt_decode_json(string $part): array
{
    $decoded = json_decode(ar_lti_b64url_decode($part), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Malformed signed token.');
    }
    return $decoded;
}

function ar_lti_fetch_jwks(string $url): array
{
    if (strpos($url, 'https://') !== 0 || !function_exists('curl_init')) {
        throw new RuntimeException('Secure Canvas key lookup is unavailable.');
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
    ));
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($body) || $status !== 200) {
        throw new RuntimeException('Canvas signing keys could not be retrieved.');
    }
    $payload = json_decode($body, true);
    if (!is_array($payload) || !isset($payload['keys']) || !is_array($payload['keys'])) {
        throw new RuntimeException('Canvas returned an invalid signing-key set.');
    }
    return $payload['keys'];
}

function ar_lti_verify_id_token(string $jwt, array $platform, string $expectedNonce): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed Canvas launch token.');
    }
    $header = ar_lti_jwt_decode_json($parts[0]);
    $claims = ar_lti_jwt_decode_json($parts[1]);
    if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        throw new RuntimeException('Canvas launch token uses an unsupported signature.');
    }

    $key = null;
    foreach (ar_lti_fetch_jwks((string) $platform['jwks_url']) as $candidate) {
        if (is_array($candidate) && ($candidate['kid'] ?? '') === $header['kid'] && ($candidate['use'] ?? 'sig') === 'sig') {
            $key = $candidate;
            break;
        }
    }
    if (!$key) {
        throw new RuntimeException('Canvas launch signing key was not recognized.');
    }
    $publicKey = openssl_pkey_get_public(ar_lti_jwk_rsa_pem($key));
    if ($publicKey === false) {
        throw new RuntimeException('Canvas launch signing key is invalid.');
    }
    $valid = openssl_verify($parts[0] . '.' . $parts[1], ar_lti_b64url_decode($parts[2]), $publicKey, OPENSSL_ALGO_SHA256);
    if ($valid !== 1) {
        throw new RuntimeException('Canvas launch signature could not be verified.');
    }

    $now = time();
    $issuer = rtrim((string) ($claims['iss'] ?? ''), '/');
    $expectedIssuer = rtrim((string) $platform['issuer'], '/');
    $audience = $claims['aud'] ?? array();
    $audience = is_array($audience) ? $audience : array($audience);
    $deployment = (string) ($claims['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? '');
    $allowedDeployments = array_map('strval', (array) $platform['deployment_ids']);
    if ($issuer !== $expectedIssuer
        || !in_array((string) $platform['client_id'], $audience, true)
        || (count($audience) > 1 && (string) ($claims['azp'] ?? '') !== (string) $platform['client_id'])
        || empty($claims['sub'])
        || empty($claims['exp']) || (int) $claims['exp'] < $now - 30
        || empty($claims['iat']) || (int) $claims['iat'] > $now + 60 || (int) $claims['iat'] < $now - 600
        || !isset($claims['nonce']) || !hash_equals($expectedNonce, (string) $claims['nonce'])
        || !in_array($deployment, $allowedDeployments, true)) {
        throw new RuntimeException('Canvas launch claims did not pass validation.');
    }
    return $claims;
}

function ar_lti_sign_jwt(array $claims): string
{
    $config = ar_lti_config();
    if ($config['private_key'] === '' || $config['key_id'] === '') {
        throw new RuntimeException('LTI signing key is not configured.');
    }
    $header = array('alg' => 'RS256', 'typ' => 'JWT', 'kid' => $config['key_id']);
    $input = ar_lti_b64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)) . '.' . ar_lti_b64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    $signature = '';
    $privateKey = openssl_pkey_get_private($config['private_key']);
    if ($privateKey === false || !openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('LTI response could not be signed.');
    }
    return $input . '.' . ar_lti_b64url_encode($signature);
}

function ar_lti_public_jwk(): array
{
    $config = ar_lti_config();
    if ($config['private_key'] === '' || $config['key_id'] === '') {
        throw new RuntimeException('LTI signing key is not configured.');
    }
    $key = openssl_pkey_get_private($config['private_key']);
    $details = $key ? openssl_pkey_get_details($key) : false;
    if (!is_array($details) || empty($details['rsa']['n']) || empty($details['rsa']['e'])) {
        throw new RuntimeException('LTI signing key is invalid.');
    }
    return array(
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $config['key_id'],
        'n' => ar_lti_b64url_encode($details['rsa']['n']),
        'e' => ar_lti_b64url_encode($details['rsa']['e']),
    );
}

