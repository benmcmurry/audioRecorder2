<?php
$alreadyDone = false;
$prompt_id = 0;
$isPromptOwner = false;
$showRecordingHistory = true;
$activityId = isset($_GET['activity_id']) ? trim((string) $_GET['activity_id']) : '';
$activityItems = array();
$activityPosition = 0;
$activityComplete = false;
$activityNotFound = false;
$canRepeatActivity = false;
$activityReplaceMode = false;
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 2) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('addUser.php');
if (isset($_GET['prompt_id'])) {$prompt_id = $_GET['prompt_id'];
$showRecordingHistory = false;
$alreadyDone = FALSE;

	$query = $elc_db->prepare("Select * from Prompts where prompt_id=?");
	$query->bind_param("s", $prompt_id);
	$query->execute();
	$result = $query->get_result();
	$result = $result->fetch_assoc();
	if (isset($result['netid']) && $result['netid'] == $netid) {
	    $isPromptOwner = true;
	}

$query2 = $elc_db->prepare("Select * from Audio_files where prompt_id=? and netid=?");
$query2->bind_param("ss", $prompt_id, $netid);
	$query2->execute();
	$result2 = $query2->get_result();
	$result2 = $result2->fetch_assoc();
	if (isset($result2) && !$isPromptOwner) {
	    $alreadyDone = TRUE;
	}
	}

if ($activityId !== '' && preg_match('/\\A[a-f0-9]{32}\\z/i', $activityId)) {
    $prompt_id = 0;
    $alreadyDone = false;
    $isPromptOwner = false;
    unset($result, $result2);
    $activityQuery = $elc_db->prepare("SELECT Activities.activity_id, Activities.netid AS activity_owner, Activities.title AS activity_title, Activity_items.item_id, Activity_items.position, Activity_items.prompt_id, Prompts.netid AS prompt_owner, Prompts.title AS prompt_title, Prompts.text, Prompts.prepare_time, Prompts.response_time, Prompts.archive, Prompts.read_prompt, Prompts.transcription FROM Activities JOIN Activity_items ON Activity_items.activity_id = Activities.activity_id JOIN Prompts ON Prompts.prompt_id = Activity_items.prompt_id WHERE Activities.activity_id = ? AND Activities.archive = 0 AND Prompts.archive = 0 ORDER BY Activity_items.position ASC");
    if ($activityQuery) {
        $activityQuery->bind_param('s', $activityId);
        $activityQuery->execute();
        $activityResult = $activityQuery->get_result();
        $canRepeatActivity = true;
        while ($activityResult && $activityRow = $activityResult->fetch_assoc()) {
            if ((string) $activityRow['activity_owner'] !== (string) $netid
                || (string) $activityRow['prompt_owner'] !== (string) $netid) {
                $canRepeatActivity = false;
            }
            $responseQuery = $elc_db->prepare("SELECT id FROM Audio_files WHERE prompt_id = ? AND netid = ? ORDER BY id DESC LIMIT 1");
            if ($responseQuery) {
                $responseQuery->bind_param('ss', $activityRow['prompt_id'], $netid);
                $responseQuery->execute();
                $responseResult = $responseQuery->get_result();
                $responseRow = $responseResult ? $responseResult->fetch_assoc() : null;
                $activityRow['response_id'] = $responseRow ? (int) $responseRow['id'] : 0;
            } else {
                $activityRow['response_id'] = 0;
            }
            $activityItems[] = $activityRow;
        }
    }
    $canRepeatActivity = $activityItems && $canRepeatActivity;
    $activityReplaceMode = $canRepeatActivity && isset($_GET['repeat']) && (string) $_GET['repeat'] === '1';

    if ($activityItems) {
        $showRecordingHistory = false;
        foreach ($activityItems as $stepIndex => $item) {
            if ($activityReplaceMode || !$item['response_id']) {
                $activityPosition = $stepIndex;
                $prompt_id = (int) $item['prompt_id'];
                $result = array(
                    'prompt_id' => $item['prompt_id'],
                    'netid' => '',
                    'title' => $item['prompt_title'],
                    'text' => $item['text'],
                    'prepare_time' => $item['prepare_time'],
                    'response_time' => $item['response_time'],
                    'archive' => (int) $item['archive'],
                    'read_prompt' => (int) $item['read_prompt'],
                    'transcription' => (int) $item['transcription'],
                );
                $isPromptOwner = false;
                $alreadyDone = false;
                break;
            }
        }
        if ($prompt_id === 0) {
            $activityComplete = true;
            $showRecordingHistory = false;
        }
    }
    $activityNotFound = !$activityItems;
} elseif ($activityId !== '') {
    $activityNotFound = true;
}

function ar_js($value)
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

$clientPrompt = ($prompt_id && isset($result) && is_array($result)) ? $result : null;
$clientResponse = ($alreadyDone && isset($result2) && is_array($result2)) ? $result2 : null;
	?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
    <script src="js/accessibility-auto-alt.js" defer></script>
    <?php include_once __DIR__ . '/includes/styles_and_scripts.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script type="text/javascript">
        var prepare_time = <?php echo ar_js($clientPrompt ? (int) $clientPrompt['prepare_time'] : 0); ?>;
        var response_time = <?php echo ar_js($clientPrompt ? (int) $clientPrompt['response_time'] : 0); ?>;
        var prompt_id = <?php echo ar_js($clientPrompt ? (int) $prompt_id : 0); ?>;
        var netid = <?php echo ar_js(isset($net_id) ? $net_id : $netid); ?>;
        var archiveStatus = <?php echo ar_js($clientPrompt ? (int) $clientPrompt['archive'] : ($prompt_id ? 1 : 2)); ?>;
        var promptText = <?php echo ar_js($clientPrompt ? $clientPrompt['text'] : ''); ?>;
        var shouldReadPrompt = <?php echo ar_js($clientPrompt && isset($clientPrompt['read_prompt']) ? (int) $clientPrompt['read_prompt'] : 1); ?>;
        var alreadyDone = <?php echo ar_js((bool) $alreadyDone); ?>;
        var arCsrfToken = <?php echo ar_js(ar_csrf_token()); ?>;
        var reviewSource = <?php echo ar_js($clientResponse ? $clientResponse['filename'] : ''); ?>;
        var reviewSourceType = <?php echo ar_js($clientResponse ? $clientResponse['filetype'] : ''); ?>;
        var reviewResponseId = <?php echo ar_js($clientResponse ? (int) $clientResponse['id'] : 0); ?>;
        var reviewTranscriptionStatus = <?php echo ar_js($clientResponse ? (string) $clientResponse['transcription_status'] : ''); ?>;
        var reviewTranscriptionText = <?php echo ar_js($clientResponse ? (string) $clientResponse['transcription_text'] : ''); ?>;
        var reviewTranscriptionSource = <?php echo ar_js($clientResponse ? (string) $clientResponse['transcription_source'] : ''); ?>;
        var activityMode = <?php echo ar_js((bool) ($activityItems && !$activityComplete)); ?>;
        var activityId = <?php echo ar_js($activityId); ?>;
        var activityTitle = <?php echo ar_js($activityItems ? (string) $activityItems[0]['activity_title'] : ''); ?>;
        var activityPosition = <?php echo ar_js((int) $activityPosition); ?>;
        var activityCount = <?php echo ar_js(count($activityItems)); ?>;
        var activityItemId = <?php echo ar_js($activityItems && !$activityComplete ? (int) $activityItems[$activityPosition]['item_id'] : 0); ?>;
        var activityReplaceMode = <?php echo ar_js((bool) $activityReplaceMode); ?>;
        var activityComplete = <?php echo ar_js((bool) $activityComplete); ?>;
        var activityNotFound = <?php echo ar_js((bool) $activityNotFound); ?>;
        var activityContinueUrl = <?php echo ar_js($activityId !== '' ? 'index.php?activity_id=' . rawurlencode($activityId) : ''); ?>;
    </script>
</head>

<body>
    <?php require_once __DIR__ . '/includes/shared-shell.php'; audio_recorder_render_header(); ?>
    <main role="main">
        <div id="mainContainer" class="container mt-5 mb-5 pb-3">

            <?php if ($activityItems && !$activityComplete) { ?>
                <section class="dashboard-card mb-4" aria-live="polite">
                    <p class="section-kicker">Multi-prompt activity</p>
                    <h1 class="dashboard-title"><?php echo htmlspecialchars((string) $activityItems[0]['activity_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="mb-0">Prompt <?php echo (int) ($activityPosition + 1); ?> of <?php echo count($activityItems); ?></p>
                    <?php if ($activityReplaceMode) { ?><p class="mt-2 mb-0">Your new responses will replace your previous responses for this activity.</p><?php } ?>
                    <?php if ($activityPosition > 0) { ?>
                        <button id="activityReadyButton" type="button" class="btn btn-primary mt-3" onclick="startRecording();">I’m ready for this prompt</button>
                    <?php } ?>
                </section>
            <?php } elseif ($activityComplete) { ?>
                <section class="dashboard-card mb-4 text-center">
                    <p class="section-kicker">Activity complete</p>
                    <h1 class="dashboard-title"><?php echo htmlspecialchars((string) $activityItems[0]['activity_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="mb-0">You have saved a response to every prompt in this activity.</p>
                    <?php if ($canRepeatActivity) { ?>
                        <p class="mt-2">Completing it again replaces only your previous responses. Other students’ recordings stay unchanged.</p>
                        <a class="btn btn-primary btn-sm mt-2" href="index.php?activity_id=<?php echo rawurlencode($activityId); ?>&amp;repeat=1">Complete activity again</a>
                    <?php } ?>
                    <a class="btn btn-outline-primary btn-sm mt-3" href="index.php">Return to Audio Recorder</a>
                </section>
            <?php } elseif ($activityNotFound) { ?>
                <section class="dashboard-card mb-4 text-center">
                    <h1 class="dashboard-title">Activity unavailable</h1>
                    <p class="mb-0">This activity link may have expired or the activity is no longer available.</p>
                </section>
            <?php } ?>



            <!-- AlreadyDone? -->
            <div id="processingScreen" class="d-none mt-5" role="status" aria-live="polite" aria-atomic="true">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                        <h5 class="card-title">Please wait</h5>
                        <p class="card-text mb-0">We are saving your recording now. Transcription may appear shortly after the upload completes.</p>
                    </div>
                </div>
            </div>
            <div id="alreadyDoneBox" class="d-none d-grid gap-2 col mx-auto mt-5">
                <div class="row" id="alreadyAnswered">
                <p class="text-center">You have already answered this prompt.</p>

                    <p class="text-center">You can play your answer below.</p>
                </div>
                <div class="row">
                    <p id="reviewRecordingLabel" class="visually-hidden">Saved response playback</p>
                    <audio class="" id="reviewRecording" controls aria-labelledby="reviewRecordingLabel">
                    </audio>
                </div>

                <div id="transcriptionProgress" class="row d-none" role="status" aria-live="polite" aria-atomic="true" aria-busy="true">
                    <div class="card mx-auto text-center transcription-progress-card">
                        <div class="card-body">
                            <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                            <h4 class="card-title">Preparing your transcript</h4>
                            <p class="card-text mb-0">Your transcript is processing. You can leave this page and return later to see it.</p>
                        </div>
                    </div>
                </div>
                <div id="transcriptionFailure" class="row d-none" role="status" aria-live="polite" aria-atomic="true">
                    <div class="alert alert-warning mx-auto transcription-progress-card mb-0">
                        <p class="mb-2">We couldn’t create your transcript. You can try again.</p>
                        <button id="retryTranscriptionButton" type="button" class="btn btn-outline-primary">Try transcription again</button>
                        <span id="retryTranscriptionStatus" class="ms-2 small" aria-live="polite"></span>
                    </div>
                </div>
                <div id="transcriptionRow" class="row d-none">
                    <h4>Transcription</h4> 
                    <div class="">
                        <label for="transcriptionBox" class="form-label">Transcription</label>
                        <textarea class="form-control" id="transcriptionBox" placeholder="Please wait for your transcription . . . ."><?php
                            if (isset($result2['transcription_text'])) { echo $result2['transcription_text']; }
                        ?></textarea>
                        <div id="transcriptionNotice" class="form-text">Transcribed by browser</div>
                    </div>
                </div>
                <div class="row justify-content-end" id="response" role="status" aria-live="polite" aria-atomic="true"></div>

                <?php if ($activityItems && !$activityComplete) { ?>
                    <div class="row justify-content-center mt-3">
                        <?php
                        $activityNextUrl = 'index.php?activity_id=' . rawurlencode($activityId);
                        $activityHasNextPrompt = $activityPosition + 1 < count($activityItems);
                        if ($activityReplaceMode && $activityHasNextPrompt) {
                            $activityNextUrl .= '&repeat=1';
                        }
                        ?>
                        <a id="activityContinueButton" class="btn btn-primary d-none" href="<?php echo htmlspecialchars($activityNextUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $activityHasNextPrompt ? 'Save &amp; Continue' : 'Finish activity'; ?></a>
                    </div>
                <?php } ?>

                <div id="repeatRecording" class="row bg-danger d-none" style="color: white; padding:2em;">
                    <p class="text-center">Please enter the password to allow the student to re-record. Please be aware that any previous recordings will be deleted.</p>
                    <label for="repeatPassword" class="form-label">Password to re-record</label>
                    <input id="repeatPassword" type="password" class="form-control" />
                </div>
            </div>

            <!-- Button display -->
            <div id="buttons" class="row gap-2 <?php echo $activityItems && $activityPosition > 0 ? 'd-none' : ''; ?>">
                <button id="testButton" type="button" class="btn btn-success" onclick="testStartRecording();">Test Microphone</button>
                <button type="button" class="btn btn-primary" onclick="startRecording();">Begin Recording</button>
            </div>

            <!-- prompt display -->
            <div id="prompt" class="row d-none">
                <?php
                if ($clientPrompt) {
                    echo "<p>" . htmlspecialchars($clientPrompt['text'], ENT_QUOTES, 'UTF-8') . "<br /> <br />";
                    echo "You have " . htmlspecialchars($clientPrompt['prepare_time'], ENT_QUOTES, 'UTF-8') . " seconds to prepare and " . htmlspecialchars($clientPrompt['response_time'], ENT_QUOTES, 'UTF-8') . " seconds to respond.</p>";
                }
                ?>
            </div>

            <!-- prepare and record display     -->
            <div id="prepareAndRecord" class="row justify-content-center">
                <div id='timer_container' class="d-flex row flex-wrap align-items-center justify-content-between d-none">
                    <img id='timeOrRecord' class="col-2 oscillate" src='images/lightbulb.jpg' alt='Lightbulb icon' />
                    <div id='timer' class='col-10 text-end' aria-live="off"></div>

                </div>
            </div>

            <!-- visualizer display -->
            <div id="visualizer" class="row justify-content-center">
                <div class='volume'>
                    <div class='volbox' id='volbox-1'></div>
                    <div class='volbox' id='volbox-2'></div>
                    <div class='volbox' id='volbox-3'></div>
                    <div class='volbox' id='volbox-4'></div>
                    <div class='volbox' id='volbox-5'></div>
                    <div class='volbox' id='volbox-6'></div>
                    <div class='volbox' id='volbox-7'></div>
                    <div class='volbox' id='volbox-8'></div>
                    <div class='volbox' id='volbox-9'></div>
                    <div class='volbox' id='volbox-10'></div>
                    <div class='volbox' id='volbox-11'></div>
                    <div class='volbox' id='volbox-12'></div>

                </div>
            </div>

            <!-- audio elements -->
            <div class="row justify-content-center">
                <audio id="live" muted></audio>
                <audio id="playback" autoplay playsinline></audio>
            </div>
           
            

        </div> <!-- end container -->
        <?php if ($showRecordingHistory) { ?>
        <div id="allRecordings" class="container mt-4 mb-5 pb-3">
            <section class="dashboard-card mb-4">
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">Audio Recorder</p>
                        <h1 class="dashboard-title">What would you like to do?</h1>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <article class="card h-100"><div class="card-body">
                            <h2 class="h5">Create or manage prompts</h2>
                            <p class="card-text">Build a prompt, share a recording link, and review responses.</p>
                            <a class="btn btn-outline-primary btn-sm" href="teacher/index.php">Open prompt workspace</a>
                        </div></article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100"><div class="card-body">
                            <h2 class="h5">Create a multi-prompt activity</h2>
                            <p class="card-text">Put prompts in order and share one activity link.</p>
                            <a class="btn btn-primary btn-sm" href="activities/">Build an activity</a>
                        </div></article>
                    </div>
                    <div class="col-md-4">
                        <article class="card h-100"><div class="card-body">
                            <h2 class="h5">My recordings</h2>
                            <p class="card-text">Review your saved recordings and transcripts.</p>
                            <button id="loadRecordingsButton" class="btn btn-outline-primary btn-sm">Load my recordings</button>
                        </div></article>
                    </div>
                </div>
                <div class="mt-3"><span id="recordingsStatus" class="small text-muted" aria-live="polite"></span></div>
            </section>
            <div id="allRecordingsResults" class="mt-4"></div>
        </div>
       <?php } ?>
     
    </main>
    <?php audio_recorder_render_footer(); ?>
    <script>
        if (archiveStatus === 1) {
            var message = document.querySelector('#mainContainer');
            message.innerHTML = "This link is no longer valid.";
        } else if (!activityComplete && !activityNotFound) {
            let myScript = document.createElement("script");
            myScript.setAttribute("src", "js/main.js");
            document.body.appendChild(myScript);

        }
        if (prompt_id === 0 && !activityComplete && !activityNotFound) {
            document.getElementById("mainContainer").style.display = "none";
            document.getElementById("allRecordings").style.display = "block";
        } else if (activityComplete || activityNotFound) {
            var allRecordings = document.getElementById("allRecordings");
            if (allRecordings) {
                allRecordings.style.display = "none";
            }
            document.getElementById("mainContainer").style.display = "block";
            document.getElementById("buttons").classList.add("d-none");
            document.getElementById("alreadyDoneBox").classList.add("d-none");
            document.getElementById("prepareAndRecord").classList.add("d-none");
            document.getElementById("visualizer").classList.add("d-none");
        }
    </script>
</body>

</html>
