<?php
require_once __DIR__ . '/common.php';
$token = (string) ($_GET['token'] ?? '');
if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    ar_lti_error(404, 'Submission link not found.');
}
$hash = hash('sha256', $token);
$db = ar_lti_db();
$query = $db->prepare('SELECT target_type, target_id, netid, response_ids FROM Lti_submission_links WHERE token_hash = ? LIMIT 1');
if (!$query) {
    ar_lti_error(503, 'Submission links are not configured.');
}
$query->bind_param('s', $hash);
$query->execute();
$link = $query->get_result()->fetch_assoc();
$ids = $link ? json_decode((string) $link['response_ids'], true) : null;
if (!$link || !is_array($ids) || !$ids) {
    ar_lti_error(404, 'Submission link expired or not found.');
}
$safeIds = array_values(array_filter(array_map('intval', $ids), static function ($id) { return $id > 0; }));
if (!$safeIds) {
    ar_lti_error(404, 'No response recordings were attached to this submission.');
}
$placeholders = implode(',', array_fill(0, count($safeIds), '?'));
$types = str_repeat('i', count($safeIds)) . 's';
$params = array_merge($safeIds, array((string) $link['netid']));
$sql = 'SELECT Audio_files.id, Audio_files.prompt_id, Audio_files.filename, Audio_files.filetype, Audio_files.transcription_text, Audio_files.transcription_status, Audio_files.date_created, Prompts.title, Prompts.text FROM Audio_files JOIN Prompts ON Prompts.prompt_id = Audio_files.prompt_id WHERE Audio_files.id IN (' . $placeholders . ') AND Audio_files.netid = ? ORDER BY FIELD(Audio_files.id, ' . $placeholders . ')';
$types .= str_repeat('i', count($safeIds));
$params = array_merge($params, $safeIds);
$query = $db->prepare($sql);
if (!$query) {
    ar_lti_error(500, 'Could not load the submission recordings.');
}
$bindArgs = array($types);
foreach ($params as $key => &$value) {
    $bindArgs[] = &$value;
}
call_user_func_array(array($query, 'bind_param'), $bindArgs);
$query->execute();
$result = $query->get_result();
$rows = array();
while ($result && $row = $result->fetch_assoc()) {
    $rows[] = $row;
}
if (count($rows) !== count(array_unique($safeIds))) {
    ar_lti_error(404, 'One or more submission recordings are no longer available.');
}
header('X-Robots-Tag: noindex, nofollow');
ar_lti_html_start('Canvas submission');
?>
<section class="dashboard-card mb-4">
    <p class="section-kicker">Canvas SpeedGrader submission</p>
    <h1><?php echo htmlspecialchars($link['target_type'] === 'activity' ? 'Multi-Prompt Activity' : 'Prompt Response', ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="mb-0">Student audio responses from this Audio Recorder assignment.</p>
</section>
<?php foreach ($rows as $position => $row) { ?>
    <article class="dashboard-card mb-3">
        <h2 class="h4">Response <?php echo (int) ($position + 1); ?>: <?php echo htmlspecialchars((string) ($row['title'] ?: 'Untitled Prompt'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p><?php echo nl2br(htmlspecialchars((string) $row['text'], ENT_QUOTES, 'UTF-8')); ?></p>
        <p class="small text-muted">Submitted <?php echo htmlspecialchars((string) $row['date_created'], ENT_QUOTES, 'UTF-8'); ?></p>
        <audio controls preload="metadata" style="width:100%" src="<?php echo htmlspecialchars(ar_lti_base_url('lti/submission-audio.php?token=' . rawurlencode($token) . '&response_id=' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>">Your browser cannot play this audio file.</audio>
        <?php if (trim((string) $row['transcription_text']) !== '') { ?>
            <h3 class="h6 mt-3">Transcript</h3>
            <div><?php echo nl2br(htmlspecialchars((string) $row['transcription_text'], ENT_QUOTES, 'UTF-8')); ?></div>
        <?php } elseif (in_array((string) $row['transcription_status'], array('pending', 'processing'), true)) { ?>
            <p class="small text-muted mt-3">Transcript is still processing.</p>
        <?php } ?>
    </article>
<?php } ?>
<?php ar_lti_html_end(); ?>
