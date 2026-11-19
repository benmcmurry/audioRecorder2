<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
include_once('../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('../phpScripts/responseHelpers.php');
include_once('../phpScripts/activityHelpers.php');

$activityId = trim((string) ($_GET['activity_id'] ?? ''));
$activity = ar_activity_owned($elc_db, $activityId, $netid);
$items = $activity ? ar_activity_items($elc_db, $activityId) : array();
$groupByPrompt = isset($_GET['group']) && $_GET['group'] === 'prompt';
$responses = array();
if ($activity && $items) {
    $query = $elc_db->prepare('SELECT Activity_items.item_id, Activity_items.position, Activity_items.prompt_id, Prompts.title, Prompts.text, Audio_files.id AS response_id, Audio_files.netid, Audio_files.filename, Audio_files.filetype, Audio_files.transcription_text, Audio_files.transcription_status, Audio_files.date_created, Users.name AS user_name FROM Activity_items JOIN Prompts ON Prompts.prompt_id = Activity_items.prompt_id JOIN Audio_files ON Audio_files.prompt_id = Activity_items.prompt_id LEFT JOIN Users ON Users.netid = Audio_files.netid WHERE Activity_items.activity_id = ? ORDER BY Activity_items.position ASC, COALESCE(Users.name, Audio_files.netid) ASC, Audio_files.date_created ASC');
    $query->bind_param('s', $activityId);
    $query->execute();
    $result = $query->get_result();
    while ($result && $row = $result->fetch_assoc()) {
        $responses[] = $row;
    }
    if (!$groupByPrompt) {
        usort($responses, function ($a, $b) {
            $nameA = ar_student_name($a);
            $nameB = ar_student_name($b);
            $nameCompare = strcasecmp($nameA, $nameB);
            if ($nameCompare !== 0) return $nameCompare;
            return (int) $a['position'] <=> (int) $b['position'];
        });
    }
}
$studentUrl = '../index.php?activity_id=' . rawurlencode($activityId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $activity ? ar_h($activity['title']) : 'Activity responses'; ?> | ELC Audio Recorder</title>
    <script src="../js/accessibility-auto-alt.js" defer></script>
    <?php include_once __DIR__ . '/../includes/styles_and_scripts.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/shared-shell.php'; audio_recorder_render_header(); ?>
    <main class="container dashboard-shell mt-4 mb-5 pb-3">
        <section class="dashboard-card mb-4">
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Activity responses</p>
                    <h1 class="dashboard-title"><?php echo $activity ? ar_h($activity['title']) : 'Activity not found'; ?></h1>
                    <?php if ($activity) { ?><p class="mb-0"><?php echo count($items); ?> prompts · <?php echo count($responses); ?> saved responses</p><?php } ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary btn-sm" href="index.php">Activity workspace</a>
                    <?php if ($activity) { ?><a class="btn btn-outline-secondary btn-sm" href="<?php echo ar_h($studentUrl); ?>" target="_blank" rel="noopener">Student link</a><?php } ?>
                </div>
            </div>
            <?php if ($activity) { ?>
                <nav class="btn-group mt-3" aria-label="Group activity responses">
                    <a class="btn btn-sm <?php echo $groupByPrompt ? 'btn-outline-primary' : 'btn-primary'; ?>" href="?activity_id=<?php echo rawurlencode($activityId); ?>&group=student" <?php echo !$groupByPrompt ? 'aria-current="page"' : ''; ?>>By student</a>
                    <a class="btn btn-sm <?php echo $groupByPrompt ? 'btn-primary' : 'btn-outline-primary'; ?>" href="?activity_id=<?php echo rawurlencode($activityId); ?>&group=prompt" <?php echo $groupByPrompt ? 'aria-current="page"' : ''; ?>>By prompt</a>
                </nav>
            <?php } ?>
        </section>

        <?php if (!$activity) { ?>
            <div class="empty-state">This activity was not found or you do not have access to it.</div>
        <?php } elseif (!$responses) { ?>
            <div class="empty-state">No students have submitted responses to this activity yet.</div>
        <?php } elseif ($groupByPrompt) { ?>
            <?php foreach ($items as $item) {
                $promptResponses = array_values(array_filter($responses, function ($response) use ($item) { return (int) $response['item_id'] === (int) $item['item_id']; })); ?>
                <section class="dashboard-card mb-3">
                    <h2 class="h4">Prompt <?php echo (int) $item['position']; ?>: <?php echo ar_h($item['title'] ?: 'Untitled Prompt'); ?></h2>
                    <p><?php echo nl2br(ar_h($item['text'])); ?></p>
                    <?php if (!$promptResponses) { ?><p class="text-muted mb-0">No responses yet.</p><?php } ?>
                    <?php foreach ($promptResponses as $response) { include __DIR__ . '/response-card.php'; } ?>
                </section>
            <?php } ?>
        <?php } else {
            $studentGroups = array();
            foreach ($responses as $response) {
                $studentKey = (string) $response['netid'];
                if (!isset($studentGroups[$studentKey])) $studentGroups[$studentKey] = array();
                $studentGroups[$studentKey][] = $response;
            }
            foreach ($studentGroups as $studentResponses) {
                $studentName = ar_student_name($studentResponses[0]); ?>
                <section class="dashboard-card mb-3">
                    <h2 class="h4"><?php echo ar_h($studentName); ?></h2>
                    <?php foreach ($studentResponses as $response) { ?>
                        <div class="border-top pt-3 mt-3">
                            <h3 class="h5">Prompt <?php echo (int) $response['position']; ?>: <?php echo ar_h($response['title'] ?: 'Untitled Prompt'); ?></h3>
                            <p><?php echo nl2br(ar_h($response['text'])); ?></p>
                            <?php include __DIR__ . '/response-card.php'; ?>
                        </div>
                    <?php } ?>
                </section>
            <?php }
        } ?>
    </main>
    <?php audio_recorder_render_footer(); ?>
</body>
</html>
