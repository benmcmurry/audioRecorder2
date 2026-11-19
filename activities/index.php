<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
include_once('../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once __DIR__ . '/../phpScripts/responseHelpers.php';
include_once('../phpScripts/activityHelpers.php');
ar_activity_ensure_read_prompt_column($elc_db);

$activityId = trim((string) ($_GET['activity_id'] ?? ''));
$activity = $activityId !== '' ? ar_activity_owned($elc_db, $activityId, $netid) : null;
$editing = (bool) $activity;
$activityItems = $editing ? ar_activity_items($elc_db, $activityId) : array();
$canEdit = $editing && !ar_activity_prompt_has_responses($elc_db, $activityId);
$libraryQuery = $elc_db->prepare('SELECT Prompts.prompt_id, Prompts.title, Prompts.text, Prompts.prepare_time, Prompts.response_time, Prompts.transcription, Prompts.read_prompt FROM Prompts WHERE Prompts.netid = ? AND Prompts.archive = 0 AND Prompts.prompt_id NOT IN (SELECT Activity_items.prompt_id FROM Activity_items) ORDER BY Prompts.date_created DESC');
$libraryQuery->bind_param('s', $netid);
$libraryQuery->execute();
$libraryResult = $libraryQuery->get_result();
$libraryPrompts = array();
while ($libraryResult && $row = $libraryResult->fetch_assoc()) {
    $libraryPrompts[] = $row;
}
$activitiesQuery = $elc_db->prepare('SELECT Activities.activity_id, Activities.title, Activities.date_created, COUNT(Audio_files.id) AS response_count FROM Activities LEFT JOIN Activity_items ON Activity_items.activity_id = Activities.activity_id LEFT JOIN Audio_files ON Audio_files.prompt_id = Activity_items.prompt_id WHERE Activities.netid = ? AND Activities.activity_id <> ? GROUP BY Activities.activity_id, Activities.title, Activities.date_created ORDER BY Activities.date_created DESC');
$excludeId = $editing ? $activityId : '';
$activitiesQuery->bind_param('ss', $netid, $excludeId);
$activitiesQuery->execute();
$activitiesResult = $activitiesQuery->get_result();
$activityRows = array();
while ($activitiesResult && $row = $activitiesResult->fetch_assoc()) {
    $activityRows[] = $row;
}

$initialItems = array();
foreach ($activityItems as $item) {
    $initialItems[] = array(
        'activity_item_id' => (int) $item['item_id'],
        'title' => (string) $item['title'],
        'text' => (string) $item['text'],
        'source_title' => (string) $item['title'],
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Prompt Activities | ELC Audio Recorder</title>
    <script src="../js/accessibility-auto-alt.js" defer></script>
    <?php include_once __DIR__ . '/../includes/styles_and_scripts.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/shared-shell.php'; audio_recorder_render_header(); ?>
    <main class="container dashboard-shell mt-4 mb-5 pb-3">
        <section class="dashboard-card mb-4">
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Activity workspace</p>
                    <h1 class="dashboard-title">Multi-Prompt Activities</h1>
                    <p class="mb-0">Combine existing prompts and new prompts in the order students will answer them.</p>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="../teacher/">Prompt library</a>
            </div>
        </section>

        <section class="dashboard-card mb-4" id="activityEditor">
            <div class="section-heading">
                <div>
                    <p class="section-kicker"><?php echo $editing ? 'Edit activity' : 'Create activity'; ?></p>
                    <h2><?php echo $editing ? ar_h($activity['title']) : 'Build an activity'; ?></h2>
                </div>
            </div>
            <?php if ($editing && !$canEdit) { ?>
                <div class="alert alert-info">This activity already has student responses. Its prompt order and wording are locked so existing work stays attached to the same prompts.</div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a class="btn btn-primary btn-sm" href="responses.php?activity_id=<?php echo rawurlencode($activityId); ?>">View activity responses</a>
                    <a class="btn btn-outline-primary btn-sm" href="../index.php?activity_id=<?php echo rawurlencode($activityId); ?>" target="_blank" rel="noopener">Open student activity</a>
                </div>
            <?php } else { ?>
                <form id="activityForm" novalidate>
                    <input type="hidden" id="activity_id" value="<?php echo $editing ? ar_h($activityId) : ''; ?>">
                    <div class="mb-3">
                        <label class="form-label" for="activity_title">Activity title</label>
                        <input class="form-control" id="activity_title" maxlength="255" required value="<?php echo $editing ? ar_h($activity['title']) : ''; ?>" placeholder="For example: Speaking Quiz 1">
                    </div>

                    <div class="border rounded p-3 mb-3">
                        <h3 class="h5">Add an existing prompt</h3>
                        <?php if (count($libraryPrompts)) { ?>
                            <div class="d-flex flex-wrap gap-2">
                                <label class="visually-hidden" for="libraryPrompt">Choose a prompt</label>
                                <select class="form-select flex-grow-1" id="libraryPrompt" style="min-width: 15rem;">
                                    <?php foreach ($libraryPrompts as $promptRow) { ?>
                                        <option value="<?php echo (int) $promptRow['prompt_id']; ?>"><?php echo ar_h(($promptRow['title'] ?: 'Untitled Prompt') . ' — ' . mb_strimwidth($promptRow['text'], 0, 100, '…')); ?></option>
                                    <?php } ?>
                                </select>
                                <button class="btn btn-outline-primary" type="button" id="addLibraryPrompt">Add to activity</button>
                            </div>
                        <?php } else { ?>
                            <p class="mb-0 text-muted">No unused prompts in your library yet. Create one below, or add prompts to the library first.</p>
                        <?php } ?>
                    </div>

                    <details class="border rounded p-3 mb-3" id="newPromptDetails">
                        <summary class="h5 mb-0">Create a prompt in this activity</summary>
                        <div class="mt-3">
                            <div class="mb-3">
                                <label class="form-label" for="newPromptTitle">Prompt title (optional)</label>
                                <input class="form-control" id="newPromptTitle" maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="newPromptText">Prompt text</label>
                                <textarea class="form-control" id="newPromptText" rows="3"></textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="newPrepareTime">Prepare time (seconds)</label>
                                    <input class="form-control" id="newPrepareTime" type="number" min="0" max="600" value="0">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="newResponseTime">Response time (seconds)</label>
                                    <input class="form-control" id="newResponseTime" type="number" min="1" max="600" value="60">
                                </div>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="newTranscription">
                                <label class="form-check-label" for="newTranscription">Allow or require a student transcript</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="newReadPrompt" checked>
                                <label class="form-check-label" for="newReadPrompt">Read the prompt aloud</label>
                            </div>
                            <button class="btn btn-outline-primary" type="button" id="addNewPrompt">Add new prompt</button>
                        </div>
                    </details>

                    <div class="mb-3">
                        <h3 class="h5">Activity order</h3>
                        <ol id="activityItems" class="list-group mb-2" aria-live="polite"></ol>
                        <p id="activityEmpty" class="text-muted">Add at least two prompts. Use the arrow buttons to change their order.</p>
                    </div>
                    <div id="activityStatus" class="small mb-3" role="status" aria-live="polite"></div>
                    <button class="btn btn-primary" id="saveActivity" type="submit">Save activity</button>
                </form>
            <?php } ?>
        </section>

        <?php if ($activityRows) { ?>
            <section class="dashboard-card">
                <div class="section-heading">
                    <div><p class="section-kicker">Your activities</p><h2>Saved activities</h2></div>
                </div>
                <div class="list-group">
                    <?php foreach ($activityRows as $row) { ?>
                        <article class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <h3 class="h5 mb-1"><?php echo ar_h($row['title']); ?></h3>
                                <span class="text-muted small"><?php echo (int) $row['response_count']; ?> response<?php echo (int) $row['response_count'] === 1 ? '' : 's'; ?></span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-primary btn-sm" href="index.php?activity_id=<?php echo rawurlencode($row['activity_id']); ?>">Edit</a>
                                <a class="btn btn-outline-primary btn-sm" href="responses.php?activity_id=<?php echo rawurlencode($row['activity_id']); ?>">View responses</a>
                                <a class="btn btn-outline-secondary btn-sm" href="../index.php?activity_id=<?php echo rawurlencode($row['activity_id']); ?>" target="_blank" rel="noopener">Student link</a>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>
    </main>
    <?php audio_recorder_render_footer(); ?>
    <script>window.arActivityInitialItems = <?php echo json_encode($initialItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>; window.arCsrfToken = <?php echo json_encode(ar_csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script src="../js/activities.js"></script>
</body>
</html>
