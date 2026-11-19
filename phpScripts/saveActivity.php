<?php
include_once('../cas-go.php');
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('activityHelpers.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ar_activity_json_response(405, array('ok' => false, 'message' => 'Method not allowed.'));
}
if (empty($netid)) {
    ar_activity_json_response(401, array('ok' => false, 'message' => 'Sign in is required.'));
}
if (!ar_valid_csrf_token($_POST['csrf_token'] ?? null)) {
    ar_activity_json_response(403, array('ok' => false, 'message' => 'Your session expired. Reload the page and try again.'));
}

$activityId = trim((string) ($_POST['activity_id'] ?? ''));
$title = trim((string) ($_POST['title'] ?? ''));
$items = json_decode((string) ($_POST['items'] ?? ''), true);
if ($title === '' || strlen($title) > 255 || !is_array($items) || count($items) < 2 || count($items) > 50) {
    ar_activity_json_response(400, array('ok' => false, 'message' => 'Add a title and between 2 and 50 prompts.'));
}
if ($activityId !== '' && (!ar_activity_valid_id($activityId) || !ar_activity_owned($elc_db, $activityId, $netid))) {
    ar_activity_json_response(403, array('ok' => false, 'message' => 'You do not have access to this activity.'));
}
if ($activityId !== '' && ar_activity_prompt_has_responses($elc_db, $activityId)) {
    ar_activity_json_response(409, array('ok' => false, 'message' => 'This activity already has responses and can no longer be reordered or changed.'));
}

ar_activity_ensure_read_prompt_column($elc_db);
$resolved = array();
foreach ($items as $index => $item) {
    if (!is_array($item)) {
        ar_activity_json_response(400, array('ok' => false, 'message' => 'One of the activity prompts is invalid.'));
    }
    $activityItemId = isset($item['activity_item_id']) ? trim((string) $item['activity_item_id']) : '';
    if ($activityItemId !== '') {
        if ($activityId === '' || !preg_match('/\\A[0-9]+\\z/', $activityItemId)) {
            ar_activity_json_response(400, array('ok' => false, 'message' => 'One of the existing activity prompts is invalid.'));
        }
        $existingItem = $elc_db->prepare('SELECT Activity_items.item_id FROM Activity_items JOIN Activities ON Activities.activity_id = Activity_items.activity_id WHERE Activity_items.item_id = ? AND Activity_items.activity_id = ? AND Activities.netid = ? LIMIT 1');
        $existingItem->bind_param('iss', $activityItemId, $activityId, $netid);
        $existingItem->execute();
        $existingResult = $existingItem->get_result();
        if (!$existingResult || !$existingResult->fetch_assoc()) {
            ar_activity_json_response(403, array('ok' => false, 'message' => 'An existing prompt does not belong to this activity.'));
        }
        $resolved[] = array('activity_item_id' => (int) $activityItemId);
        continue;
    }
    $sourceId = isset($item['source_prompt_id']) ? trim((string) $item['source_prompt_id']) : '';
    if ($sourceId !== '') {
        if (!preg_match('/\\A[0-9]+\\z/', $sourceId)) {
            ar_activity_json_response(400, array('ok' => false, 'message' => 'One of the selected prompts is invalid.'));
        }
        $query = $elc_db->prepare('SELECT prompt_id, title, text, prepare_time, response_time, transcription, read_prompt FROM Prompts WHERE prompt_id = ? AND netid = ? AND archive = 0 LIMIT 1');
        $query->bind_param('ss', $sourceId, $netid);
        $query->execute();
        $result = $query->get_result();
        $source = $result ? $result->fetch_assoc() : null;
        if (!$source) {
            ar_activity_json_response(403, array('ok' => false, 'message' => 'A selected prompt is unavailable or is not yours.'));
        }
        $resolved[] = array('source_prompt_id' => (int) $source['prompt_id'], 'snapshot' => $source);
        continue;
    }

    $newPrompt = isset($item['new_prompt']) && is_array($item['new_prompt']) ? $item['new_prompt'] : array();
    $promptTitle = trim((string) ($newPrompt['title'] ?? ''));
    $promptText = trim((string) ($newPrompt['text'] ?? ''));
    $prepare = filter_var($newPrompt['prepare_time'] ?? 0, FILTER_VALIDATE_INT);
    $respond = filter_var($newPrompt['response_time'] ?? 0, FILTER_VALIDATE_INT);
    if ($promptText === '' || strlen($promptText) > 10000 || $prepare === false || $respond === false || $prepare < 0 || $respond < 1 || $prepare > 600 || $respond > 600 || strlen($promptTitle) > 255) {
        ar_activity_json_response(400, array('ok' => false, 'message' => 'Each new prompt needs text, a valid title, and times between 0 and 600 seconds (response time must be at least 1).'));
    }
    $resolved[] = array(
        'source_prompt_id' => null,
        'snapshot' => array(
            'title' => $promptTitle !== '' ? $promptTitle : 'Activity prompt ' . ($index + 1),
            'text' => $promptText,
            'prepare_time' => (int) $prepare,
            'response_time' => (int) $respond,
            'transcription' => !empty($newPrompt['transcription']) ? 1 : 0,
            'read_prompt' => !array_key_exists('read_prompt', $newPrompt) || !empty($newPrompt['read_prompt']) ? 1 : 0,
        ),
        'create_library_prompt' => true,
    );
}

$isNew = $activityId === '';
if ($isNew) {
    $activityId = bin2hex(random_bytes(16));
}
$elc_db->begin_transaction();
try {
    if ($isNew) {
        $query = $elc_db->prepare('INSERT INTO Activities (activity_id, netid, title, archive, date_created) VALUES (?, ?, ?, 0, NOW())');
        $query->bind_param('sss', $activityId, $netid, $title);
    } else {
        $query = $elc_db->prepare('UPDATE Activities SET title = ? WHERE activity_id = ? AND netid = ?');
        $query->bind_param('sss', $title, $activityId, $netid);
    }
    if (!$query->execute()) {
        throw new RuntimeException('Could not save the activity.');
    }

    if (!$isNew) {
        $offsetPositions = $elc_db->prepare('UPDATE Activity_items SET position = position + 1000 WHERE activity_id = ?');
        $offsetPositions->bind_param('s', $activityId);
        if (!$offsetPositions->execute()) {
            throw new RuntimeException('Could not prepare activity order changes.');
        }
    }

    $keptItemIds = array();
    $position = 0;
    foreach ($resolved as $entry) {
        $position++;
        if (isset($entry['activity_item_id'])) {
            $updateItem = $elc_db->prepare('UPDATE Activity_items SET position = ? WHERE item_id = ? AND activity_id = ?');
            $existingItemId = (int) $entry['activity_item_id'];
            $updateItem->bind_param('iis', $position, $existingItemId, $activityId);
            if (!$updateItem->execute()) {
                throw new RuntimeException('Could not update activity order.');
            }
            $keptItemIds[] = $existingItemId;
            continue;
        }
        $snapshot = $entry['snapshot'];
        $sourceId = $entry['source_prompt_id'];
        if (!empty($entry['create_library_prompt'])) {
            $sourceInsert = $elc_db->prepare('INSERT INTO Prompts (prompt_id, netid, title, text, prepare_time, response_time, transcription, read_prompt, archive, date_created) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, NOW())');
            $sourceTitle = $snapshot['title'];
            $sourceText = $snapshot['text'];
            $sourcePrepare = (string) $snapshot['prepare_time'];
            $sourceRespond = (string) $snapshot['response_time'];
            $sourceTranscription = (string) $snapshot['transcription'];
            $sourceRead = (string) $snapshot['read_prompt'];
            $sourceInsert->bind_param('sssssss', $netid, $sourceTitle, $sourceText, $sourcePrepare, $sourceRespond, $sourceTranscription, $sourceRead);
            if (!$sourceInsert->execute()) {
                throw new RuntimeException('Could not save a new reusable prompt.');
            }
            $sourceId = (int) $sourceInsert->insert_id;
        }

        $copy = $elc_db->prepare('INSERT INTO Prompts (prompt_id, netid, title, text, prepare_time, response_time, transcription, read_prompt, archive, date_created) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, NOW())');
        $copyTitle = (string) $snapshot['title'];
        $copyText = (string) $snapshot['text'];
        $copyPrepare = (string) $snapshot['prepare_time'];
        $copyRespond = (string) $snapshot['response_time'];
        $copyTranscription = (string) $snapshot['transcription'];
        $copyRead = (string) $snapshot['read_prompt'];
        $copy->bind_param('sssssss', $netid, $copyTitle, $copyText, $copyPrepare, $copyRespond, $copyTranscription, $copyRead);
        if (!$copy->execute()) {
            throw new RuntimeException('Could not save a prompt snapshot.');
        }
        $snapshotPromptId = (int) $copy->insert_id;

        $insertItem = $elc_db->prepare('INSERT INTO Activity_items (activity_id, position, prompt_id, source_prompt_id) VALUES (?, ?, ?, ?)');
        $insertItem->bind_param('siii', $activityId, $position, $snapshotPromptId, $sourceId);
        if (!$insertItem->execute()) {
            throw new RuntimeException('Could not save activity order.');
        }
        $keptItemIds[] = (int) $insertItem->insert_id;
    }
    if (!$isNew) {
        $existingIdsQuery = $elc_db->prepare('SELECT item_id, prompt_id FROM Activity_items WHERE activity_id = ?');
        $existingIdsQuery->bind_param('s', $activityId);
        $existingIdsQuery->execute();
        $existingIdsResult = $existingIdsQuery->get_result();
        $removedItems = array();
        while ($existingIdsResult && $existing = $existingIdsResult->fetch_assoc()) {
            if (!in_array((int) $existing['item_id'], $keptItemIds, true)) {
                $removedItems[] = array('item_id' => (int) $existing['item_id'], 'prompt_id' => (int) $existing['prompt_id']);
            }
        }
        foreach ($removedItems as $removedItem) {
            $delete = $elc_db->prepare('DELETE FROM Activity_items WHERE item_id = ? AND activity_id = ?');
            $delete->bind_param('is', $removedItem['item_id'], $activityId);
            if (!$delete->execute()) {
                throw new RuntimeException('Could not remove a prompt from the activity.');
            }
            $deletePrompt = $elc_db->prepare('DELETE FROM Prompts WHERE prompt_id = ? AND netid = ?');
            $deletePrompt->bind_param('is', $removedItem['prompt_id'], $netid);
            if (!$deletePrompt->execute()) {
                throw new RuntimeException('Could not remove an unused prompt snapshot.');
            }
        }
    }
    $elc_db->commit();
} catch (Throwable $error) {
    $elc_db->rollback();
    error_log('[audioRecorder-activity] save failed: ' . $error->getMessage());
    ar_activity_json_response(500, array('ok' => false, 'message' => 'The activity could not be saved. Please try again.'));
}

ar_activity_json_response(200, array('ok' => true, 'activity_id' => $activityId, 'url' => '../index.php?activity_id=' . rawurlencode($activityId)));
