<?php

function ar_openai_activity_clean_code($value)
{
    if (!is_scalar($value)) {
        return null;
    }

    $value = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $value);
    return $value === '' ? null : substr($value, 0, 100);
}

function ar_openai_activity_begin($db, $operation, $netid, $model, $relatedType, $relatedId)
{
    if (!($db instanceof mysqli)
        || !in_array($operation, array('speech_generation', 'transcription'), true)
        || !in_array($relatedType, array('prompt', 'recording'), true)) {
        error_log('[audioRecorder] OpenAI activity audit could not start; request was not sent.');
        return false;
    }

    $netid = trim((string) $netid);
    $netid = $netid === '' ? null : substr($netid, 0, 255);
    $model = substr((string) $model, 0, 120);
    $relatedId = trim((string) $relatedId);
    $relatedId = $relatedId === '' ? null : substr($relatedId, 0, 64);

    try {
        $statement = $db->prepare('INSERT INTO OpenAI_activity_log (netid, operation, model, outcome, related_type, related_id) VALUES (?, ?, ?, \'pending\', ?, ?)');
        if (!$statement) {
            error_log('[audioRecorder] OpenAI activity audit insert could not be prepared; request was not sent.');
            return false;
        }
        $statement->bind_param('sssss', $netid, $operation, $model, $relatedType, $relatedId);
        if (!$statement->execute()) {
            $statement->close();
            error_log('[audioRecorder] OpenAI activity audit insert failed; request was not sent.');
            return false;
        }

        $activityId = (int) $statement->insert_id;
        $statement->close();
        return $activityId > 0 ? $activityId : false;
    } catch (Throwable $error) {
        error_log('[audioRecorder] OpenAI activity audit insert failed; request was not sent.');
        return false;
    }
}

function ar_openai_activity_finish($db, $activityId, $outcome, $httpStatus, $errorCode, $errorType, $durationMs)
{
    if (!($db instanceof mysqli)
        || (int) $activityId <= 0
        || !in_array($outcome, array('succeeded', 'failed'), true)) {
        return false;
    }

    $httpStatus = $httpStatus === null ? null : (int) $httpStatus;
    if ($httpStatus !== null && ($httpStatus < 100 || $httpStatus > 599)) {
        $httpStatus = null;
    }
    $errorCode = ar_openai_activity_clean_code($errorCode);
    $errorType = ar_openai_activity_clean_code($errorType);
    $durationMs = max(0, min(2147483647, (int) $durationMs));
    $activityId = (int) $activityId;

    try {
        $statement = $db->prepare('UPDATE OpenAI_activity_log SET outcome = ?, http_status = ?, error_code = ?, error_type = ?, duration_ms = ? WHERE id = ?');
        if (!$statement) {
            error_log('[audioRecorder] OpenAI activity audit result could not be prepared.');
            return false;
        }
        $statement->bind_param('sissii', $outcome, $httpStatus, $errorCode, $errorType, $durationMs, $activityId);
        $ok = $statement->execute();
        $statement->close();
        if (!$ok) {
            error_log('[audioRecorder] OpenAI activity audit result could not be saved.');
        }
        return $ok;
    } catch (Throwable $error) {
        error_log('[audioRecorder] OpenAI activity audit result could not be saved.');
        return false;
    }
}

function ar_openai_activity_error_details($responseBody)
{
    $decoded = json_decode((string) $responseBody, true);
    $error = is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])
        ? $decoded['error']
        : array();

    return array(
        'code' => ar_openai_activity_clean_code(isset($error['code']) ? $error['code'] : null),
        'type' => ar_openai_activity_clean_code(isset($error['type']) ? $error['type'] : null),
    );
}

function ar_openai_activity_duration_ms($startedAt)
{
    return max(0, (int) round((microtime(true) - (float) $startedAt) * 1000));
}

?>
