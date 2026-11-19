<?php
require_once __DIR__ . '/common.php';

function ar_lti_canvas_access_token(array $platform, array $launch): string
{
    if (!in_array('https://purl.imsglobal.org/spec/lti-ags/scope/score', (array) ($launch['ags_scopes'] ?? array()), true)) {
        throw new RuntimeException('The Canvas developer key does not grant the AGS Score scope.');
    }
    $now = time();
    $assertion = ar_lti_sign_jwt(array(
        'iss' => (string) $platform['client_id'],
        'sub' => (string) $platform['client_id'],
        'aud' => (string) $platform['token_url'],
        'iat' => $now,
        'exp' => $now + 180,
        'jti' => bin2hex(random_bytes(24)),
    ));
    $curl = curl_init((string) $platform['token_url']);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'),
        CURLOPT_POSTFIELDS => http_build_query(array(
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $assertion,
            'scope' => 'https://purl.imsglobal.org/spec/lti-ags/scope/score',
        ), '', '&', PHP_QUERY_RFC3986),
    ));
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $decoded = is_string($body) ? json_decode($body, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
        throw new RuntimeException('Canvas did not issue an AGS access token.');
    }
    return (string) $decoded['access_token'];
}

function ar_lti_post_score(string $lineitemUrl, string $accessToken, array $payload): void
{
    if (strpos($lineitemUrl, 'https://') !== 0 || !function_exists('curl_init')) {
        throw new RuntimeException('The Canvas AGS line item URL is invalid.');
    }
    $url = rtrim($lineitemUrl, '/') . '/scores';
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/vnd.ims.lis.v1.score+json', 'Authorization: Bearer ' . $accessToken),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
    ));
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException('Canvas rejected the AGS submission details (' . $status . ').');
    }
}

/**
 * Called after a Canvas-launched prompt or activity has all of its response audio saved.
 * On non-LTI requests this is a no-op. Returns false if the Canvas submission update failed.
 */
function ar_lti_publish_completed_submission(string $targetType, string $targetId, string $netid): bool
{
    $launch = $_SESSION['ar_lti_launch'] ?? null;
    $user = ar_lti_launch_user();
    if (!is_array($launch) || !$user) {
        return true;
    }
    if ((string) $user['netid'] !== $netid || !in_array($targetType, array('prompt', 'activity'), true)) {
        return false;
    }
    $targetParts = parse_url((string) ($launch['target_link_uri'] ?? ''));
    $targetQuery = array();
    if (is_array($targetParts) && !empty($targetParts['query'])) {
        parse_str($targetParts['query'], $targetQuery);
    }
    $resourceKey = (string) ($targetQuery['resource_key'] ?? '');
    if (!preg_match('/\A[a-f0-9]{64}\z/', $resourceKey)) {
        return false;
    }
    $resourceDb = ar_lti_db();
    $resourceKeyHash = hash('sha256', $resourceKey);
    $resourceQuery = $resourceDb->prepare('SELECT owner_netid, target_type, target_id FROM Lti_resources WHERE resource_key_hash = ? AND issuer = ? AND deployment_id = ? LIMIT 1');
    if (!$resourceQuery) {
        return false;
    }
    $launchIssuer = (string) $launch['issuer'];
    $launchDeployment = (string) $launch['deployment_id'];
    $resourceQuery->bind_param('sss', $resourceKeyHash, $launchIssuer, $launchDeployment);
    $resourceQuery->execute();
    $authorizedResource = $resourceQuery->get_result()->fetch_assoc();
    if (!$authorizedResource || $authorizedResource['target_type'] !== $targetType || (string) $authorizedResource['target_id'] !== $targetId) {
        return false;
    }
    $lineitem = (string) ($launch['ags_lineitem'] ?? '');
    if ($lineitem === '' || !in_array('https://purl.imsglobal.org/spec/lti-ags/scope/score', (array) ($launch['ags_scopes'] ?? array()), true)) {
        error_log('[audioRecorder-lti] submission details not sent: assignment launch has no AGS score permission or line item.');
        return false;
    }
    try {
        $db = $resourceDb;
        $responseRows = array();
        if ($targetType === 'prompt') {
            if (!preg_match('/\A[0-9]+\z/', $targetId)) {
                return false;
            }
            $query = $db->prepare('SELECT id, date_created FROM Audio_files WHERE prompt_id = ? AND netid = ? ORDER BY date_created DESC LIMIT 1');
            $query->bind_param('ss', $targetId, $netid);
            $query->execute();
            $result = $query->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $responseRows[] = $row;
            }
        } else {
            if (!preg_match('/\A[a-f0-9]{32}\z/', $targetId)) {
                return false;
            }
            $items = $db->prepare('SELECT Activity_items.prompt_id FROM Activity_items JOIN Activities ON Activities.activity_id = Activity_items.activity_id WHERE Activity_items.activity_id = ? AND Activities.netid = ? ORDER BY Activity_items.position ASC');
            $items->bind_param('ss', $targetId, $authorizedResource['owner_netid']);
            $items->execute();
            $itemResult = $items->get_result();
            $promptIds = array();
            while ($itemResult && $item = $itemResult->fetch_assoc()) {
                $promptIds[] = (string) $item['prompt_id'];
            }
            if (count($promptIds) < 2) {
                return false;
            }
            foreach ($promptIds as $promptId) {
                $response = $db->prepare('SELECT id, date_created FROM Audio_files WHERE prompt_id = ? AND netid = ? ORDER BY date_created DESC LIMIT 1');
                $response->bind_param('ss', $promptId, $netid);
                $response->execute();
                $responseResult = $response->get_result();
                $row = $responseResult ? $responseResult->fetch_assoc() : null;
                if (!$row) {
                    return false;
                }
                $responseRows[] = $row;
            }
        }
        if (!$responseRows) {
            return false;
        }
        $ids = array_map(static function ($row) { return (int) $row['id']; }, $responseRows);
        $scopeHash = hash('sha256', implode("\0", array(
            (string) $launch['issuer'], (string) $launch['deployment_id'], (string) $launch['context_id'],
            (string) $launch['resource_link_id'], $netid,
        )));
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $responseIds = json_encode($ids);
        $targetIdDb = (string) $targetId;
        $targetTypeDb = $targetType;
        $issuer = (string) $launch['issuer'];
        $deploymentId = (string) $launch['deployment_id'];
        $contextId = (string) ($launch['context_id'] ?? '');
        $resourceLinkId = (string) ($launch['resource_link_id'] ?? '');
        $scopeQuery = $db->prepare('SELECT id FROM Lti_submission_links WHERE submission_scope_hash = ? LIMIT 1');
        if (!$scopeQuery) {
            throw new RuntimeException('LTI submission storage is not initialized.');
        }
        $scopeQuery->bind_param('s', $scopeHash);
        $scopeQuery->execute();
        $scopeResult = $scopeQuery->get_result();
        $existing = $scopeResult ? $scopeResult->fetch_assoc() : null;
        if ($existing) {
            $update = $db->prepare("UPDATE Lti_submission_links SET token_hash = ?, target_type = ?, target_id = ?, response_ids = ?, lineitem_url = ?, delivery_status = 'pending', delivery_error = '', updated_at = NOW() WHERE id = ?");
            $rowId = (int) $existing['id'];
            $update->bind_param('sssssi', $tokenHash, $targetTypeDb, $targetIdDb, $responseIds, $lineitem, $rowId);
            if (!$update->execute()) {
                throw new RuntimeException('Could not update the Canvas submission link.');
            }
        } else {
            $insert = $db->prepare("INSERT INTO Lti_submission_links (token_hash, issuer, deployment_id, context_id, resource_link_id, netid, target_type, target_id, response_ids, submission_scope_hash, lineitem_url, delivery_status, delivery_error, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', '', NOW(), NOW())");
            if (!$insert) {
                throw new RuntimeException('LTI submission storage is not initialized.');
            }
            $insert->bind_param('sssssssssss', $tokenHash, $issuer, $deploymentId, $contextId, $resourceLinkId, $netid, $targetTypeDb, $targetIdDb, $responseIds, $scopeHash, $lineitem);
            if (!$insert->execute()) {
                throw new RuntimeException('Could not create the Canvas submission link.');
            }
        }

        $submissionUrl = ar_lti_base_url('lti/submission.php?token=' . rawurlencode($token));
        $platform = ar_lti_platform_for_issuer($issuer, (string) $launch['client_id']);
        if (!$platform) {
            throw new RuntimeException('The active Canvas platform is no longer configured.');
        }
        $submittedAt = gmdate('Y-m-d\TH:i:s.000\Z');
        $payload = array(
            'userId' => (string) $launch['subject'],
            'timestamp' => $submittedAt,
            'activityProgress' => 'Completed',
            'gradingProgress' => 'Pending',
            'submission' => array('submittedAt' => $submittedAt),
            'https://canvas.instructure.com/lti/submission' => array(
                'new_submission' => true,
                'preserve_score' => true,
                'submission_type' => 'basic_lti_launch',
                'submission_data' => $submissionUrl,
                'submitted_at' => $submittedAt,
            ),
        );
        $accessToken = ar_lti_canvas_access_token($platform, $launch);
        ar_lti_post_score($lineitem, $accessToken, $payload);
        $success = $db->prepare("UPDATE Lti_submission_links SET delivery_status = 'sent', delivery_error = '', updated_at = NOW() WHERE submission_scope_hash = ?");
        $success->bind_param('s', $scopeHash);
        $success->execute();
        return true;
    } catch (Throwable $error) {
        error_log('[audioRecorder-lti] AGS submission detail delivery failed: ' . $error->getMessage());
        if (isset($scopeHash) && isset($db) && $db instanceof mysqli) {
            $failed = $db->prepare("UPDATE Lti_submission_links SET delivery_status = 'failed', delivery_error = ?, updated_at = NOW() WHERE submission_scope_hash = ?");
            if ($failed) {
                $message = substr($error->getMessage(), 0, 500);
                $failed->bind_param('ss', $message, $scopeHash);
                $failed->execute();
            }
        }
        return false;
    }
}
