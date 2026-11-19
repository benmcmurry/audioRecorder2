<?php
require_once __DIR__ . '/common.php';
$launch = $_SESSION['ar_lti_launch'] ?? null;
$hasLaunch = is_array($launch);
$hasAllowedRole = $hasLaunch && ar_lti_has_teacher_role($launch);
$hasLtiUser = ar_lti_launch_user() !== null;
if (!$hasLaunch || !$hasAllowedRole || !$hasLtiUser) {
    $roles = $hasLaunch && isset($launch['roles']) && is_array($launch['roles']) ? $launch['roles'] : array();
    error_log('[audioRecorder-lti] Content picker access denied: launch=' . ($hasLaunch ? 'yes' : 'no')
        . '; allowed_role=' . ($hasAllowedRole ? 'yes' : 'no')
        . '; lti_user=' . ($hasLtiUser ? 'yes' : 'no')
        . '; roles=' . json_encode(array_values(array_map('strval', $roles))));
    ar_lti_error(403, 'Only a Canvas teacher can select assignment content.');
}
$settings = $launch['deep_link_settings'] ?? array();
$acceptedTypes = isset($settings['accept_types']) && is_array($settings['accept_types']) ? $settings['accept_types'] : array();
if (!in_array('ltiResourceLink', $acceptedTypes, true)) {
    ar_lti_error(400, 'Canvas did not allow an Audio Recorder assignment link in this selection.');
}
$db = ar_lti_db();
$netid = (string) ar_lti_launch_user()['netid'];
$promptQuery = $db->prepare('SELECT p.prompt_id, p.title, p.text FROM Prompts p WHERE p.netid = ? AND p.archive = 0 AND p.prompt_id NOT IN (SELECT ai.prompt_id FROM Activity_items ai) ORDER BY p.date_created DESC');
$promptQuery->bind_param('s', $netid);
$promptQuery->execute();
$promptResult = $promptQuery->get_result();
$promptRows = array();
while ($promptResult && $row = $promptResult->fetch_assoc()) {
    $promptRows[] = $row;
}
$activityQuery = $db->prepare('SELECT a.activity_id, a.title, COUNT(ai.item_id) AS item_count FROM Activities a LEFT JOIN Activity_items ai ON ai.activity_id = a.activity_id WHERE a.netid = ? AND a.archive = 0 GROUP BY a.activity_id, a.title ORDER BY a.date_created DESC');
$activityQuery->bind_param('s', $netid);
$activityQuery->execute();
$activityResult = $activityQuery->get_result();
$activityRows = array();
while ($activityResult && $row = $activityResult->fetch_assoc()) {
    $activityRows[] = $row;
}
$_SESSION['ar_lti_picker_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['ar_lti_picker_csrf'];
$linked = isset($_SESSION['ar_lti_identity_key']['netid']) && strpos((string) $_SESSION['ar_lti_identity_key']['netid'], 'lti_') !== 0;
$linkHandoffToken = !$linked ? ar_lti_create_link_handoff((array) ($_SESSION['ar_lti_identity_key'] ?? array())) : '';
ar_lti_html_start('Choose assignment content');
?>
<section class="dashboard-card mb-4">
    <div class="section-heading">
        <div><p class="section-kicker">Canvas assignment setup</p><h1>Choose Audio Recorder content</h1></div>
        <?php if (!$linked) { ?><form method="post" action="<?php echo htmlspecialchars(ar_lti_base_url('lti/link.php'), ENT_QUOTES, 'UTF-8'); ?>" target="_top"><input type="hidden" name="handoff" value="<?php echo htmlspecialchars($linkHandoffToken, ENT_QUOTES, 'UTF-8'); ?>"><button class="btn btn-outline-primary btn-sm" type="submit">Link my existing account</button></form><?php } ?>
    </div>
    <p class="mb-0">Select one prompt or activity for this Canvas assignment. Students will record inside Canvas. Canvas remains responsible for grades.</p>
</section>

<section class="dashboard-card mb-4">
    <h2 class="h4">Existing prompts</h2>
    <?php if (!$promptRows) { ?><p class="text-muted">No reusable prompts are available in this account.</p><?php } else { ?>
        <form method="post" action="<?php echo htmlspecialchars(ar_lti_base_url('lti/deep-link-return.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="kind" value="prompt">
            <?php foreach ($promptRows as $index => $row) { ?>
                <label class="list-group-item d-flex gap-2 align-items-start">
                    <input class="form-check-input mt-1" type="radio" name="target_id" value="<?php echo (int) $row['prompt_id']; ?>" required <?php echo $index === 0 ? 'checked' : ''; ?>>
                    <span><strong><?php echo htmlspecialchars($row['title'] ?: 'Untitled Prompt', ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="text-muted small"><?php echo htmlspecialchars(mb_strimwidth((string) $row['text'], 0, 180, '…'), ENT_QUOTES, 'UTF-8'); ?></span></span>
                </label>
            <?php } ?>
            <div class="mt-3"><button class="btn btn-primary" type="submit" name="selected_prompt" value="1">Add selected prompt to Canvas</button></div>
        </form>
    <?php } ?>
</section>

<section class="dashboard-card mb-4">
    <h2 class="h4">Existing multi-prompt activities</h2>
    <?php if (!$activityRows) { ?><p class="text-muted">No saved activities are available in this account yet.</p><?php } else { ?>
        <form method="post" action="<?php echo htmlspecialchars(ar_lti_base_url('lti/deep-link-return.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="kind" value="activity">
            <?php foreach ($activityRows as $index => $row) { ?>
                <label class="list-group-item d-flex gap-2 align-items-start">
                    <input class="form-check-input mt-1" type="radio" name="target_id" value="<?php echo htmlspecialchars($row['activity_id'], ENT_QUOTES, 'UTF-8'); ?>" required <?php echo $index === 0 ? 'checked' : ''; ?>>
                    <span><strong><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="text-muted small"><?php echo (int) $row['item_count']; ?> prompts in order</span></span>
                </label>
            <?php } ?>
            <div class="mt-3"><button class="btn btn-primary" type="submit">Add selected activity to Canvas</button></div>
        </form>
    <?php } ?>
</section>

<section class="dashboard-card mb-4">
    <h2 class="h4">Create a prompt in Canvas</h2>
    <form method="post" action="<?php echo htmlspecialchars(ar_lti_base_url('lti/deep-link-return.php'), ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="kind" value="new_prompt">
        <div class="col-12"><label class="form-label" for="new_title">Prompt title</label><input class="form-control" id="new_title" name="title" maxlength="255" required></div>
        <div class="col-12"><label class="form-label" for="new_text">Prompt text</label><textarea class="form-control" id="new_text" name="text" rows="3" maxlength="10000" required></textarea></div>
        <div class="col-sm-6"><label class="form-label" for="new_prepare">Prepare time in seconds</label><input class="form-control" id="new_prepare" name="prepare_time" type="number" min="0" max="600" value="0" required></div>
        <div class="col-sm-6"><label class="form-label" for="new_response">Response time in seconds</label><input class="form-control" id="new_response" name="response_time" type="number" min="1" max="600" value="60" required></div>
        <div class="col-12"><button class="btn btn-outline-primary" type="submit">Create and add prompt</button></div>
    </form>
</section>

<section class="dashboard-card">
    <h2 class="h4">Create a multi-prompt activity in Canvas</h2>
    <p>Choose two or more prompts in order. You can also add a new prompt to the activity.</p>
    <form id="newActivityForm" class="row g-3">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="app_csrf" value="<?php echo htmlspecialchars(ar_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-12"><label class="form-label" for="activity_title">Activity title</label><input class="form-control" id="activity_title" name="title" maxlength="255" required></div>
        <?php foreach ($promptRows as $row) { ?>
            <label class="list-group-item d-flex gap-2 align-items-start col-12"><input class="form-check-input mt-1 activity-picker-prompt" type="checkbox" name="prompt_ids[]" value="<?php echo (int) $row['prompt_id']; ?>" data-title="<?php echo htmlspecialchars($row['title'] ?: 'Untitled Prompt', ENT_QUOTES, 'UTF-8'); ?>"><span><strong><?php echo htmlspecialchars($row['title'] ?: 'Untitled Prompt', ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="text-muted small"><?php echo htmlspecialchars(mb_strimwidth((string) $row['text'], 0, 140, '…'), ENT_QUOTES, 'UTF-8'); ?></span></span></label>
        <?php } ?>
        <div class="col-12 border rounded p-3">
            <h3 class="h6">Optional new prompt</h3>
            <label class="form-label" for="activity_new_text">Prompt text</label>
            <textarea class="form-control mb-2" id="activity_new_text" rows="2" maxlength="10000"></textarea>
            <label class="form-label" for="activity_new_title">Prompt title</label>
            <input class="form-control mb-2" id="activity_new_title" maxlength="255">
            <div class="row g-2"><div class="col-sm-6"><label class="form-label" for="activity_new_prepare">Prepare seconds</label><input class="form-control" id="activity_new_prepare" type="number" min="0" max="600" value="0"></div><div class="col-sm-6"><label class="form-label" for="activity_new_response">Response seconds</label><input class="form-control" id="activity_new_response" type="number" min="1" max="600" value="60"></div></div>
            <button class="btn btn-outline-primary btn-sm mt-2" id="addActivityNewPrompt" type="button">Add this prompt to the activity</button>
        </div>
        <div class="col-12"><h3 class="h6">Prompt order</h3><ol id="activityPickerOrder" class="list-group" aria-live="polite"><li class="list-group-item text-muted" id="activityPickerEmpty">Select prompts above, then use the arrows to set their order.</li></ol></div>
        <div class="col-12"><p id="activityCreateStatus" class="small" aria-live="polite"></p><button class="btn btn-primary" type="submit">Create activity and add to Canvas</button></div>
    </form>
</section>
<script>
const activityPickerItems = [];
const activityPickerOrder = document.getElementById('activityPickerOrder');
function renderActivityPickerOrder() {
  activityPickerOrder.innerHTML = '';
  if (!activityPickerItems.length) {
    const empty = document.createElement('li'); empty.className = 'list-group-item text-muted'; empty.textContent = 'Select prompts above, then use the arrows to set their order.'; activityPickerOrder.appendChild(empty); return;
  }
  activityPickerItems.forEach((item, index) => {
    const row = document.createElement('li'); row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
    const label = document.createElement('span'); label.textContent = item.label; row.appendChild(label);
    const controls = document.createElement('span'); controls.className = 'd-flex gap-1';
    const up = document.createElement('button'); up.type = 'button'; up.className = 'btn btn-outline-secondary btn-sm'; up.textContent = 'Move up'; up.setAttribute('aria-label', 'Move ' + item.label + ' up'); up.disabled = index === 0;
    up.addEventListener('click', () => { [activityPickerItems[index - 1], activityPickerItems[index]] = [activityPickerItems[index], activityPickerItems[index - 1]]; renderActivityPickerOrder(); });
    const down = document.createElement('button'); down.type = 'button'; down.className = 'btn btn-outline-secondary btn-sm'; down.textContent = 'Move down'; down.setAttribute('aria-label', 'Move ' + item.label + ' down'); down.disabled = index === activityPickerItems.length - 1;
    down.addEventListener('click', () => { [activityPickerItems[index + 1], activityPickerItems[index]] = [activityPickerItems[index], activityPickerItems[index + 1]]; renderActivityPickerOrder(); });
    const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-outline-danger btn-sm'; remove.textContent = 'Remove';
    remove.addEventListener('click', () => { if (item.checkbox) item.checkbox.checked = false; activityPickerItems.splice(index, 1); renderActivityPickerOrder(); });
    controls.append(up, down, remove); row.appendChild(controls); activityPickerOrder.appendChild(row);
  });
}
document.querySelectorAll('.activity-picker-prompt').forEach((checkbox) => checkbox.addEventListener('change', function () {
  const existingIndex = activityPickerItems.findIndex((item) => item.checkbox === checkbox);
  if (checkbox.checked && existingIndex === -1) activityPickerItems.push({ label: checkbox.dataset.title, checkbox, payload: { source_prompt_id: checkbox.value } });
  if (!checkbox.checked && existingIndex !== -1) activityPickerItems.splice(existingIndex, 1);
  renderActivityPickerOrder();
}));
document.getElementById('addActivityNewPrompt').addEventListener('click', function () {
  const text = document.getElementById('activity_new_text').value.trim();
  if (!text) { document.getElementById('activityCreateStatus').textContent = 'Enter prompt text before adding it.'; return; }
  const title = document.getElementById('activity_new_title').value.trim() || ('New prompt ' + (activityPickerItems.length + 1));
  activityPickerItems.push({ label: title, payload: { new_prompt: { title: document.getElementById('activity_new_title').value, text, prepare_time: document.getElementById('activity_new_prepare').value, response_time: document.getElementById('activity_new_response').value, transcription: 0, read_prompt: 1 } } });
  document.getElementById('activity_new_text').value = '';
  document.getElementById('activityCreateStatus').textContent = '';
  renderActivityPickerOrder();
});
document.getElementById('newActivityForm').addEventListener('submit', async function (event) {
  event.preventDefault();
  const form = event.currentTarget;
  const status = document.getElementById('activityCreateStatus');
  const selected = activityPickerItems.map((item) => item.payload);
  if (selected.length < 2) { status.textContent = 'Add at least two prompts to make an activity.'; return; }
  const payload = new FormData();
  payload.append('csrf_token', form.querySelector('[name="app_csrf"]').value);
  payload.append('title', document.getElementById('activity_title').value);
  payload.append('items', JSON.stringify(selected));
  status.textContent = 'Saving activity…';
  try {
    const response = await fetch(<?php echo json_encode(ar_lti_base_url('phpScripts/saveActivity.php')); ?>, { method: 'POST', credentials: 'same-origin', body: payload });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.message || 'The activity could not be saved.');
    const selection = new FormData();
    selection.append('csrf', form.querySelector('[name="csrf"]').value);
    selection.append('kind', 'activity');
    selection.append('target_id', result.activity_id);
    const finalResponse = await fetch(<?php echo json_encode(ar_lti_base_url('lti/deep-link-return.php')); ?>, { method: 'POST', credentials: 'same-origin', body: selection });
    document.open(); document.write(await finalResponse.text()); document.close();
  } catch (error) { status.textContent = error.message; }
});
</script>
<?php ar_lti_html_end(); ?>
