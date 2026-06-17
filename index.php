<?php
$alreadyDone = false;
$prompt_id = 0;
$isPromptOwner = false;
$showRecordingHistory = true;
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("cas-go.php");
include_once('../../connectFiles/connect_ar.php');
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
        var reviewSource = <?php echo ar_js($clientResponse ? $clientResponse['filename'] : ''); ?>;
        var reviewSourceType = <?php echo ar_js($clientResponse ? $clientResponse['filetype'] : ''); ?>;
    </script>
</head>

<body>
    <?php require_once __DIR__ . '/includes/shared-shell.php'; audio_recorder_render_header(); ?>
    <main role="main">
        <div id="mainContainer" class="container mt-5 mb-5 pb-3">



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

                <div id="transcriptionRow" class="row">
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

                <div id="repeatRecording" class="row bg-danger d-none" style="color: white; padding:2em;">
                    <p class="text-center">Please enter the password to allow the student to re-record. Please be aware that any previous recordings will be deleted.</p>
                    <label for="repeatPassword" class="form-label">Password to re-record</label>
                    <input id="repeatPassword" type="password" class="form-control" />
                </div>
            </div>

            <!-- Button display -->
            <div id="buttons" class="row gap-2">
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
        <div id="allRecordings" class="container mt-5 mb-5 pb-3">
            <nav class="container mt-5">
                <a id="createPrompt" class='btn btn-primary me-3' href='teacher/index.php'>Teacher Area</a>
                <button id="loadRecordingsButton" class="btn btn-outline-primary">Load My Recordings</button>
                <span id="recordingsStatus" class="ms-2 small text-muted" aria-live="polite"></span>
            </nav>
            <div id="allRecordingsResults" class="mt-4"></div>
        </div>
       <?php } ?>
     
    </main>
    <?php audio_recorder_render_footer(); ?>
    <script>
        if (archiveStatus === 1) {
            var message = document.querySelector('#mainContainer');
            message.innerHTML = "This link is no longer valid.";
        } else {
            let myScript = document.createElement("script");
            myScript.setAttribute("src", "js/main.js");
            document.body.appendChild(myScript);

        }
        if (prompt_id === 0) {
            document.getElementById("mainContainer").style.display = "none";
            document.getElementById("allRecordings").style.display = "block";
           
        }
    </script>
</body>

</html>
