<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/feedback.php';
require_once dirname(__DIR__) . '/phpScripts/openAiActivityCommon.php';

ar_feedback_require_admin();
$db = ar_include_db();

function ar_api_activity_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ar_api_activity_valid_date($value)
{
    $value = trim((string) $value);
    if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
        return '';
    }

    list($year, $month, $day) = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year) ? $value : '';
}

function ar_api_activity_bind($statement, $types, array &$values)
{
    if ($types === '') {
        return true;
    }

    $arguments = array($types);
    foreach ($values as $index => &$value) {
        $arguments[] = &$value;
    }
    unset($value);

    return call_user_func_array(array($statement, 'bind_param'), $arguments);
}

function ar_api_activity_page_url($page, array $filters)
{
    $query = array_filter($filters, function ($value) {
        return $value !== '' && $value !== null;
    });
    $query['page'] = (int) $page;
    return ar_web_root() . '/admin/api-activity.php?' . http_build_query($query);
}

$filters = array(
    'from' => ar_api_activity_valid_date($_GET['from'] ?? ''),
    'to' => ar_api_activity_valid_date($_GET['to'] ?? ''),
    'netid' => mb_substr(trim((string) ($_GET['netid'] ?? '')), 0, 255),
    'operation' => trim((string) ($_GET['operation'] ?? '')),
    'outcome' => trim((string) ($_GET['outcome'] ?? '')),
);
if (!in_array($filters['operation'], array('', 'speech_generation', 'transcription'), true)) {
    $filters['operation'] = '';
}
if (!in_array($filters['outcome'], array('', 'pending', 'succeeded', 'failed'), true)) {
    $filters['outcome'] = '';
}

$where = array();
$types = '';
$values = array();
if ($filters['from'] !== '') {
    $where[] = 'attempted_at >= ?';
    $types .= 's';
    $values[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where[] = 'attempted_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $types .= 's';
    $values[] = $filters['to'];
}
if ($filters['netid'] !== '') {
    $where[] = 'netid = ?';
    $types .= 's';
    $values[] = $filters['netid'];
}
if ($filters['operation'] !== '') {
    $where[] = 'operation = ?';
    $types .= 's';
    $values[] = $filters['operation'];
}
if ($filters['outcome'] !== '') {
    $where[] = 'outcome = ?';
    $types .= 's';
    $values[] = $filters['outcome'];
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pageSize = 100;
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalRows = 0;
$entries = array();
$queryError = '';

try {
    $count = $db->prepare('SELECT COUNT(*) FROM OpenAI_activity_log' . $whereSql);
    if (!$count || !ar_api_activity_bind($count, $types, $values) || !$count->execute()) {
        throw new RuntimeException('Could not query activity count.');
    }
    $count->bind_result($totalRows);
    $count->fetch();
    $count->close();
    $totalRows = (int) $totalRows;

    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $selectValues = $values;
    $selectValues[] = $pageSize;
    $selectValues[] = $offset;
    $selectTypes = $types . 'ii';
    $select = $db->prepare('SELECT id, attempted_at, netid, operation, model, outcome, http_status, error_code, error_type, duration_ms, related_type, related_id FROM OpenAI_activity_log' . $whereSql . ' ORDER BY attempted_at DESC, id DESC LIMIT ? OFFSET ?');
    if (!$select || !ar_api_activity_bind($select, $selectTypes, $selectValues) || !$select->execute()) {
        throw new RuntimeException('Could not query activity entries.');
    }
    $result = $select->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $entries[] = $row;
    }
    $select->close();
} catch (Throwable $error) {
    $queryError = 'Activity data is unavailable. Confirm the OpenAI activity migration has been applied.';
    http_response_code(503);
}

$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$startRow = $totalRows === 0 ? 0 : (($page - 1) * $pageSize) + 1;
$endRow = min($totalRows, $page * $pageSize);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenAI API Activity | Audio Recorder</title>
    <?php include_once dirname(__DIR__) . '/includes/styles_and_scripts.php'; ?>
    <style>
        .api-activity-main { width: min(1280px, calc(100vw - 2rem)); margin: 2rem auto 3rem; }
        .api-activity-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .85rem; align-items: end; margin: 1.25rem 0; }
        .api-activity-filters label { display: block; margin-bottom: .25rem; }
        .api-activity-table { width: 100%; border-collapse: collapse; }
        .api-activity-table th, .api-activity-table td { padding: .65rem; border-bottom: 1px solid #d7e0e8; text-align: left; vertical-align: top; }
        .api-activity-table td { overflow-wrap: anywhere; }
        .api-activity-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1rem; }
        @media (max-width: 760px) { .api-activity-table { display: block; overflow-x: auto; } }
    </style>
</head>
<body>
<?php require_once dirname(__DIR__) . '/includes/shared-shell.php'; audio_recorder_render_header(); ?>
<main id="main" class="api-activity-main ui-page-shell">
    <h1>OpenAI API Activity</h1>
    <p>Recent speech generation and transcription requests. Prompt text, transcripts, audio, and API keys are not stored here.</p>

    <?php if ($queryError !== '') { ?>
        <div class="alert alert-warning" role="status"><?php echo ar_api_activity_escape($queryError); ?></div>
    <?php } else { ?>
        <form class="api-activity-filters" method="get" action="<?php echo ar_api_activity_escape(ar_web_root() . '/admin/api-activity.php'); ?>">
            <div><label for="activity-from">From</label><input class="form-control" id="activity-from" type="date" name="from" value="<?php echo ar_api_activity_escape($filters['from']); ?>"></div>
            <div><label for="activity-to">To</label><input class="form-control" id="activity-to" type="date" name="to" value="<?php echo ar_api_activity_escape($filters['to']); ?>"></div>
            <div><label for="activity-netid">NetID</label><input class="form-control" id="activity-netid" type="search" name="netid" maxlength="255" value="<?php echo ar_api_activity_escape($filters['netid']); ?>"></div>
            <div><label for="activity-operation">Operation</label><select class="form-select" id="activity-operation" name="operation">
                <option value="">All operations</option>
                <option value="speech_generation"<?php echo $filters['operation'] === 'speech_generation' ? ' selected' : ''; ?>>Speech generation</option>
                <option value="transcription"<?php echo $filters['operation'] === 'transcription' ? ' selected' : ''; ?>>Transcription</option>
            </select></div>
            <div><label for="activity-outcome">Outcome</label><select class="form-select" id="activity-outcome" name="outcome">
                <option value="">All outcomes</option>
                <option value="succeeded"<?php echo $filters['outcome'] === 'succeeded' ? ' selected' : ''; ?>>Succeeded</option>
                <option value="failed"<?php echo $filters['outcome'] === 'failed' ? ' selected' : ''; ?>>Failed</option>
                <option value="pending"<?php echo $filters['outcome'] === 'pending' ? ' selected' : ''; ?>>Pending / unknown</option>
            </select></div>
            <div><button class="btn-byu" type="submit">Filter activity</button></div>
        </form>

        <p>Showing <?php echo (int) $startRow; ?>–<?php echo (int) $endRow; ?> of <?php echo (int) $totalRows; ?> entries. Newest first; 100 entries per page.</p>
        <div class="table-responsive">
            <table class="api-activity-table">
                <thead><tr><th>Time</th><th>NetID</th><th>Operation</th><th>Model</th><th>Outcome</th><th>HTTP</th><th>Error</th><th>Duration</th><th>Related record</th></tr></thead>
                <tbody>
                <?php foreach ($entries as $entry) { ?>
                    <tr>
                        <td><?php echo ar_api_activity_escape($entry['attempted_at']); ?></td>
                        <td><?php echo ar_api_activity_escape($entry['netid'] !== null && $entry['netid'] !== '' ? $entry['netid'] : 'Unknown'); ?></td>
                        <td><?php echo $entry['operation'] === 'speech_generation' ? 'Speech generation' : 'Transcription'; ?></td>
                        <td><?php echo ar_api_activity_escape($entry['model']); ?></td>
                        <td><?php echo ar_api_activity_escape(ucfirst((string) $entry['outcome'])); ?></td>
                        <td><?php echo $entry['http_status'] === null ? '—' : (int) $entry['http_status']; ?></td>
                        <td><?php
                            $errorParts = array_filter(array((string) $entry['error_code'], (string) $entry['error_type']), function ($value) { return $value !== ''; });
                            echo $errorParts ? ar_api_activity_escape(implode(' / ', $errorParts)) : '—';
                        ?></td>
                        <td><?php echo $entry['duration_ms'] === null ? '—' : (int) $entry['duration_ms'] . ' ms'; ?></td>
                        <td><?php
                            if ($entry['related_id'] === null || $entry['related_id'] === '') {
                                echo '—';
                            } else {
                                echo ar_api_activity_escape(ucfirst((string) $entry['related_type']) . ' #' . (string) $entry['related_id']);
                            }
                        ?></td>
                    </tr>
                <?php } ?>
                <?php if (!$entries) { ?><tr><td colspan="9">No API activity matches these filters.</td></tr><?php } ?>
                </tbody>
            </table>
        </div>
        <nav class="api-activity-pagination" aria-label="Activity pages">
            <span>Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
            <div>
                <?php if ($page > 1) { ?><a class="btn-outline-byu" href="<?php echo ar_api_activity_escape(ar_api_activity_page_url($page - 1, $filters)); ?>">Previous</a><?php } ?>
                <?php if ($page < $totalPages) { ?><a class="btn-outline-byu" href="<?php echo ar_api_activity_escape(ar_api_activity_page_url($page + 1, $filters)); ?>">Next</a><?php } ?>
            </div>
        </nav>
    <?php } ?>
</main>
<?php audio_recorder_render_footer(); ?>
</body>
</html>
