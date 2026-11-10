<?php
require_once __DIR__ . '/../auth/common.php';

const AR_FEEDBACK_MAX_SCREENSHOT_BYTES = 4000000;
const AR_FEEDBACK_RETENTION_DAYS = 90;

function ar_feedback_categories(): array
{
    return array(
        'visual' => 'Visual problem',
        'activity' => 'Activity problem',
        'content' => 'Incorrect content',
        'other' => 'Other',
    );
}

function ar_feedback_csrf_token(): string
{
    if (empty($_SESSION['ar_feedback_csrf_token'])) {
        $_SESSION['ar_feedback_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['ar_feedback_csrf_token'];
}

function ar_feedback_csrf_valid(): bool
{
    $submitted = (string) ($_SERVER['HTTP_X_FEEDBACK_CSRF'] ?? $_POST['csrf_token'] ?? '');
    return $submitted !== '' && hash_equals(ar_feedback_csrf_token(), $submitted);
}

function ar_feedback_storage_root(): string
{
    $configured = trim((string) getenv('AR_FEEDBACK_STORAGE_ROOT'));
    if ($configured !== '') {
        if ($configured[0] !== DIRECTORY_SEPARATOR) {
            throw new RuntimeException('AR_FEEDBACK_STORAGE_ROOT must be an absolute private path.');
        }
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $resolvedConfigured = realpath($configured);
        $candidate = $resolvedConfigured !== false ? $resolvedConfigured : $configured;
        if ($documentRoot !== false) {
            $documentRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR);
            $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
            if ($candidate === $documentRoot || strpos($candidate, $documentRoot . DIRECTORY_SEPARATOR) === 0) {
                throw new RuntimeException('AR_FEEDBACK_STORAGE_ROOT must be outside the document root.');
            }
        }

        return rtrim($candidate, DIRECTORY_SEPARATOR);
    }

    return dirname(__DIR__, 3) . '/private-storage/audio-recorder-feedback';
}

function ar_feedback_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ar_feedback_admin_allowed(?string $netid = null): bool
{
    $user = ar_get_session_user();
    $netid = trim((string) ($netid ?? ($user['netid'] ?? '')));
    if ($netid === '') {
        return false;
    }

    $db = ar_include_db();
    $statement = $db->prepare('SELECT netid FROM AudioRecorder_admins WHERE netid = ? LIMIT 1');
    if (!$statement) {
        return false;
    }
    $statement->bind_param('s', $netid);
    $statement->execute();
    $result = $statement->get_result();
    $allowed = $result && $result->num_rows > 0;
    $statement->close();

    return $allowed;
}

function ar_feedback_require_admin(): void
{
    if (ar_feedback_admin_allowed()) {
        return;
    }

    http_response_code(403);
    echo 'You do not have permission to review feedback reports.';
    exit;
}

function ar_feedback_request_value(string $key, int $maxLength): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $maxLength);
}

function ar_feedback_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function ar_feedback_store_screenshot(string $reportId): ?array
{
    $upload = $_FILES['screenshot'] ?? null;
    if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
        || (int) ($upload['size'] ?? 0) < 1
        || (int) ($upload['size'] ?? 0) > AR_FEEDBACK_MAX_SCREENSHOT_BYTES) {
        throw new RuntimeException('The screenshot could not be accepted. Please use a PNG under 4 MB.');
    }

    $temporaryPath = (string) ($upload['tmp_name'] ?? '');
    $imageInfo = $temporaryPath !== '' ? @getimagesize($temporaryPath) : false;
    if (!$imageInfo
        || ($imageInfo['mime'] ?? '') !== 'image/png'
        || (int) ($imageInfo[0] ?? 0) < 1
        || (int) ($imageInfo[1] ?? 0) < 1
        || (int) ($imageInfo[0] ?? 0) > 5000
        || (int) ($imageInfo[1] ?? 0) > 5000) {
        throw new RuntimeException('The screenshot must be a valid PNG no larger than 5000 pixels in either direction.');
    }

    $relativePath = gmdate('Y/m') . '/' . $reportId . '.png';
    $absolutePath = ar_feedback_storage_root() . '/' . $relativePath;
    $directory = dirname($absolutePath);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('The screenshot could not be stored.');
    }
    @chmod($directory, 0700);
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $resolvedDirectory = realpath($directory);
    if ($resolvedDirectory === false || ($documentRoot !== false && ($resolvedDirectory === $documentRoot || strpos($resolvedDirectory, rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0))) {
        throw new RuntimeException('Feedback screenshots must be stored outside the web directory.');
    }
    if (!move_uploaded_file($temporaryPath, $absolutePath)) {
        throw new RuntimeException('The screenshot could not be stored.');
    }
    @chmod($absolutePath, 0600);

    return array(
        'path' => $relativePath,
        'sha256' => hash_file('sha256', $absolutePath),
        'bytes' => (int) (filesize($absolutePath) ?: 0),
    );
}

function ar_feedback_find(string $id): ?array
{
    $db = ar_include_db();
    $statement = $db->prepare('SELECT * FROM Feedback_reports WHERE id = ? LIMIT 1');
    if (!$statement) {
        return null;
    }
    $statement->bind_param('s', $id);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $statement->close();

    return is_array($row) ? $row : null;
}

function ar_feedback_screenshot_absolute_path(array $report): ?string
{
    $relative = (string) ($report['screenshot_path'] ?? '');
    if ($relative === '' || strpos($relative, '..') !== false || !preg_match('#^\d{4}/\d{2}/[a-f0-9-]+\.png$#i', $relative)) {
        return null;
    }

    $root = realpath(ar_feedback_storage_root());
    $path = realpath(ar_feedback_storage_root() . '/' . $relative);
    if ($root === false || $path === false || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        return null;
    }

    return $path;
}

function ar_feedback_json_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => $message));
    exit;
}

