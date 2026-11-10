<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/feedback.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ar_feedback_json_error(405, 'Use POST to submit feedback.');
}
if (!ar_feedback_csrf_valid()) {
    ar_feedback_json_error(403, 'Invalid feedback request. Refresh the page and try again.');
}

$category = ar_feedback_request_value('category', 40);
$description = ar_feedback_request_value('description', 2000);
$email = ar_feedback_request_value('contact_email', 191);
$pageUrl = ar_feedback_request_value('page_url', 2000);
$pageTitle = ar_feedback_request_value('page_title', 255);
$viewport = ar_feedback_request_value('viewport', 64);
$userAgent = ar_feedback_request_value('user_agent', 1000);
$pageParts = parse_url($pageUrl);

if (!array_key_exists($category, ar_feedback_categories())) {
    ar_feedback_json_error(422, 'Choose a feedback category.');
}
if (mb_strlen($description) < 20) {
    ar_feedback_json_error(422, 'Please describe the problem in at least 20 characters.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ar_feedback_json_error(422, 'Enter a valid email address or leave it blank.');
}
if (!is_array($pageParts) || !isset($pageParts['scheme'], $pageParts['host']) || !in_array(strtolower($pageParts['scheme']), array('http', 'https'), true)) {
    ar_feedback_json_error(422, 'The reported page must be a valid Audio Recorder page.');
}
$expectedHost = parse_url(ar_public_origin(), PHP_URL_HOST);
if (!is_string($expectedHost) || strcasecmp((string) $pageParts['host'], $expectedHost) !== 0) {
    ar_feedback_json_error(422, 'The reported page must be on Audio Recorder.');
}
$originParts = parse_url(ar_public_origin());
$pagePort = (int) ($pageParts['port'] ?? (strtolower((string) $pageParts['scheme']) === 'https' ? 443 : 80));
$originPort = (int) ($originParts['port'] ?? (strtolower((string) ($originParts['scheme'] ?? 'https')) === 'https' ? 443 : 80));
$siteRoot = rtrim(ar_web_root(), '/');
$pagePath = (string) ($pageParts['path'] ?? '/');
if (!is_array($originParts)
    || strcasecmp((string) $pageParts['scheme'], (string) ($originParts['scheme'] ?? '')) !== 0
    || $pagePort !== $originPort
    || ($siteRoot !== '' && $pagePath !== $siteRoot && strpos($pagePath, $siteRoot . '/') !== 0)) {
    ar_feedback_json_error(422, 'The reported page must be on Audio Recorder.');
}

$user = ar_get_session_user();
$reportId = ar_feedback_uuid();
$db = ar_include_db();
$screenshot = null;
$screenshotWarning = false;
try {
    $screenshot = ar_feedback_store_screenshot($reportId);
} catch (Throwable $error) {
    $screenshotWarning = true;
    error_log('Audio Recorder feedback screenshot could not be included: ' . $error->getMessage());
}
try {
    $statement = $db->prepare('INSERT INTO Feedback_reports (id, reporter_netid, reporter_email, category, description, page_url, page_title, viewport, user_agent, screenshot_path, screenshot_sha256, screenshot_bytes, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'open\', NOW(), NOW())');
    if (!$statement) {
        throw new RuntimeException('The report could not be saved. Please try again.');
    }
    $screenshotPath = $screenshot['path'] ?? null;
    $screenshotSha = $screenshot['sha256'] ?? null;
    $screenshotBytes = $screenshot['bytes'] ?? null;
    $reporterNetid = (string) ($user['netid'] ?? '');
    $statement->bind_param('sssssssssssi', $reportId, $reporterNetid, $email, $category, $description, $pageUrl, $pageTitle, $viewport, $userAgent, $screenshotPath, $screenshotSha, $screenshotBytes);
    if (!$statement->execute()) {
        throw new RuntimeException('The report could not be saved. Please try again.');
    }
    $statement->close();
} catch (Throwable $error) {
    if ($screenshot) {
        $path = ar_feedback_storage_root() . '/' . $screenshot['path'];
        if (is_file($path)) {
            @unlink($path);
        }
    }
    error_log('Audio Recorder feedback submission failed: ' . $error->getMessage());
    ar_feedback_json_error($error instanceof RuntimeException ? 422 : 500, $error->getMessage());
}

http_response_code(201);
echo json_encode(array('ok' => true, 'message' => 'Thank you. Your feedback was sent to the Audio Recorder administrators.' . ($screenshotWarning ? ' The report was saved without its screenshot.' : '')));
