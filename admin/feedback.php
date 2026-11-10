<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/feedback.php';

ar_feedback_require_admin();
$db = ar_include_db();
$reportId = trim((string) ($_GET['id'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'open'));
$statuses = array('all', 'open', 'in_review', 'resolved', 'dismissed');
if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = 'open';
}
$report = $reportId !== '' ? ar_feedback_find($reportId) : null;
$notice = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!ar_feedback_csrf_valid()) {
        http_response_code(403);
        exit('Invalid request token.');
    }
    $reportId = trim((string) ($_POST['id'] ?? ''));
    $newStatus = trim((string) ($_POST['status'] ?? ''));
    $notes = mb_substr(trim((string) ($_POST['admin_notes'] ?? '')), 0, 10000);
    $existingReport = ar_feedback_find($reportId);
    if (!in_array($newStatus, array('open', 'in_review', 'resolved', 'dismissed'), true) || !$existingReport) {
        http_response_code(422);
        exit('The report update is invalid.');
    }
    $reviewer = (string) (ar_get_session_user()['netid'] ?? '');
    $resolvedAt = in_array($newStatus, array('resolved', 'dismissed'), true)
        ? (!empty($existingReport['resolved_at']) ? $existingReport['resolved_at'] : date('Y-m-d H:i:s'))
        : null;
    $statement = $db->prepare('UPDATE Feedback_reports SET status = ?, admin_notes = ?, reviewed_by_netid = ?, resolved_at = ?, updated_at = NOW() WHERE id = ?');
    if (!$statement) {
        http_response_code(500);
        exit('The report could not be updated.');
    }
    $statement->bind_param('sssss', $newStatus, $notes, $reviewer, $resolvedAt, $reportId);
    if (!$statement->execute()) {
        http_response_code(500);
        exit('The report could not be updated.');
    }
    $statement->close();
    header('Location: ' . ar_web_root() . '/admin/feedback.php?id=' . rawurlencode($reportId));
    exit;
}

if ($statusFilter === 'all') {
    $statement = $db->prepare('SELECT id, category, page_title, page_url, status, created_at FROM Feedback_reports ORDER BY created_at DESC LIMIT 200');
} else {
    $statement = $db->prepare('SELECT id, category, page_title, page_url, status, created_at FROM Feedback_reports WHERE status = ? ORDER BY created_at DESC LIMIT 200');
    $statement->bind_param('s', $statusFilter);
}
$statement->execute();
$result = $statement->get_result();
$reports = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();
$statement->close();
$token = ar_feedback_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback Reports | Audio Recorder</title>
    <?php include_once dirname(__DIR__) . '/includes/styles_and_scripts.php'; ?>
    <style>
        .feedback-admin-main { width: min(1120px, calc(100vw - 2rem)); margin: 2rem auto 3rem; }
        .feedback-admin-table { width: 100%; border-collapse: collapse; }
        .feedback-admin-table th, .feedback-admin-table td { padding: .65rem; border-bottom: 1px solid #d7e0e8; text-align: left; vertical-align: top; }
        .feedback-admin-table td:first-child { overflow-wrap: anywhere; }
        .feedback-report-detail { margin: 1.5rem 0; padding: 1rem; border: 1px solid var(--ui-border); border-radius: 16px; background: var(--ui-surface); box-shadow: var(--ui-shadow); }
        .feedback-report-detail textarea { width: 100%; min-height: 7rem; }
        .feedback-report-screenshot { display: block; width: min(100%, 900px); height: auto; border: 1px solid var(--ui-border); border-radius: 8px; }
        .feedback-admin-filters { display: flex; flex-wrap: wrap; gap: .5rem; margin: 1rem 0; }
        @media (max-width: 700px) { .feedback-admin-table { display: block; overflow-x: auto; } }
    </style>
</head>
<body>
<?php require_once dirname(__DIR__) . '/includes/shared-shell.php'; audio_recorder_render_header(); ?>
<main id="main" class="feedback-admin-main ui-page-shell">
    <h1>Feedback Reports</h1>
    <p>Review reports submitted from the cougar feedback button.</p>
    <nav class="feedback-admin-filters" aria-label="Report status">
        <?php foreach ($statuses as $status) { if ($status === 'all') { $label = 'All'; } else { $label = ucwords(str_replace('_', ' ', $status)); } ?>
            <a class="btn-outline-byu" href="<?php echo ar_feedback_escape(ar_web_root() . '/admin/feedback.php?status=' . rawurlencode($status)); ?>"><?php echo ar_feedback_escape($label); ?></a>
        <?php } ?>
    </nav>
    <?php if ($reportId !== '' && !$report) { ?><p role="status">That report was not found.</p><?php } ?>
    <?php if ($report) { ?>
        <section class="feedback-report-detail" aria-labelledby="feedback-report-title">
            <h2 id="feedback-report-title">Report <?php echo ar_feedback_escape(substr($report['id'], 0, 8)); ?></h2>
            <dl>
                <dt>Submitted</dt><dd><?php echo ar_feedback_escape($report['created_at']); ?></dd>
                <dt>Reporter</dt><dd><?php echo ar_feedback_escape($report['reporter_netid']); ?><?php if (!empty($report['reporter_email'])) { ?> (<?php echo ar_feedback_escape($report['reporter_email']); ?>)<?php } ?></dd>
                <dt>Category</dt><dd><?php echo ar_feedback_escape(ar_feedback_categories()[$report['category']] ?? $report['category']); ?></dd>
                <dt>Page</dt><dd><a href="<?php echo ar_feedback_escape($report['page_url']); ?>"><?php echo ar_feedback_escape($report['page_title'] ?: $report['page_url']); ?></a></dd>
                <dt>Viewport</dt><dd><?php echo ar_feedback_escape($report['viewport']); ?></dd>
                <dt>Browser</dt><dd><?php echo ar_feedback_escape($report['user_agent']); ?></dd>
            </dl>
            <h3>Description</h3><p><?php echo nl2br(ar_feedback_escape($report['description'])); ?></p>
            <?php $screenshotPath = ar_feedback_screenshot_absolute_path($report); if ($screenshotPath) { ?>
                <h3>Screenshot</h3>
                <img class="feedback-report-screenshot" src="<?php echo ar_feedback_escape(ar_web_root() . '/admin/feedback-screenshot.php?id=' . rawurlencode($report['id'])); ?>" alt="Screenshot attached to this report">
            <?php } ?>
            <form method="post" action="<?php echo ar_feedback_escape(ar_web_root() . '/admin/feedback.php?id=' . rawurlencode($report['id'])); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo ar_feedback_escape($token); ?>">
                <input type="hidden" name="id" value="<?php echo ar_feedback_escape($report['id']); ?>">
                <label for="report-status">Status</label>
                <select id="report-status" name="status" class="form-select">
                    <?php foreach (array('open' => 'Open', 'in_review' => 'In review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed') as $key => $label) { ?>
                        <option value="<?php echo ar_feedback_escape($key); ?>"<?php echo $report['status'] === $key ? ' selected' : ''; ?>><?php echo ar_feedback_escape($label); ?></option>
                    <?php } ?>
                </select>
                <label for="admin-notes">Admin notes</label>
                <textarea id="admin-notes" name="admin_notes" class="form-control"><?php echo ar_feedback_escape($report['admin_notes']); ?></textarea>
                <button class="btn-byu mt-3" type="submit">Save report</button>
            </form>
        </section>
    <?php } ?>
    <div class="table-responsive">
        <table class="feedback-admin-table">
            <thead><tr><th>Submitted</th><th>Category</th><th>Page</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($reports as $row) { ?>
                <tr>
                    <td><a href="<?php echo ar_feedback_escape(ar_web_root() . '/admin/feedback.php?id=' . rawurlencode($row['id'])); ?>"><?php echo ar_feedback_escape($row['created_at']); ?></a></td>
                    <td><?php echo ar_feedback_escape(ar_feedback_categories()[$row['category']] ?? $row['category']); ?></td>
                    <td><?php echo ar_feedback_escape($row['page_title'] ?: $row['page_url']); ?></td>
                    <td><?php echo ar_feedback_escape(ucwords(str_replace('_', ' ', $row['status']))); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php if (!$reports) { ?><p>No feedback reports match this filter.</p><?php } ?>
    </div>
</main>
<?php audio_recorder_render_footer(); ?>
</body>
</html>
