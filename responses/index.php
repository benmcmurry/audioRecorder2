<?php
$prompt_id = isset($_GET['prompt_id']) ? $_GET['prompt_id'] : '';
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('../phpScripts/responseHelpers.php');

$promptRow = ar_prompt_for_owner($elc_db, $prompt_id, $netid);
if (!$promptRow) {
    http_response_code(403);
    echo "You do not have access to this prompt.";
    exit;
}

$responses = ar_prompt_responses($elc_db, $prompt_id);
$responseCount = count($responses);
$checked = ((int) $promptRow['transcription'] === 1) ? "checked" : "";
$readPromptChecked = (!isset($promptRow['read_prompt']) || (int) $promptRow['read_prompt'] === 1) ? "checked" : "";
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$promptUrl = $scheme . '://' . $host . ar_web_root() . '/index.php?prompt_id=' . urlencode($prompt_id);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
    <script src="../js/accessibility-auto-alt.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="../css/style.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script type="text/javascript"></script>
    <style>

    </style>

</head>

<body>
    <header id="header" class="p-2 bg-byu-navy text-white fixed-top">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div id="title">
                    <?php echo "<a href='" . $app_root . "/index.php'>ELC Audio Recorder</a>" ?>
                </div>
                <div id="user" class="text-end">
                    <?php echo $login; ?>
                </div>
            </div>

        </div>
    </header>
    <nav class="container mt-5 dashboard-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <a id="createPrompt" class='button btn btn-outline-primary btn-sm' href="../teacher/">Return to Prompt List</a>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?php echo ar_h($promptUrl); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">Open Prompt</a>
                <button type="button" class="btn btn-outline-primary btn-sm" id="copyPromptLink" data-prompt-url="<?php echo ar_h($promptUrl); ?>" onclick="copyPromptLink()">Copy Student Link</button>
                <a href="../phpScripts/downloadPromptFiles.php?prompt_id=<?php echo urlencode($prompt_id); ?>&type=audio" class="btn btn-outline-primary btn-sm">Download All Audio</a>
                <a href="../phpScripts/downloadPromptFiles.php?prompt_id=<?php echo urlencode($prompt_id); ?>&type=transcripts" class="btn btn-outline-primary btn-sm">Download All Transcriptions</a>
            </div>
        </div>
    </nav>
    <main role="main">
        <div class="container dashboard-shell mt-4 mb-5 pb-3">
            <section class="dashboard-card mb-4">
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">Prompt Editor</p>
                        <h1><?php echo ar_h($promptRow['title'] ? $promptRow['title'] : 'Untitled Prompt'); ?></h1>
                    </div>
                    <button class='btn btn-primary btn-sm' id='save' onclick="save('<?php echo ar_h($prompt_id); ?>');">Save Prompt</button>
                </div>
                <form id="updateForm">
                    <div class="mb-3">
                        <label for="prompt_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="prompt_title" aria-describedby="prompt_titleHelp" value="<?php echo ar_h($promptRow['title']); ?>">
                        <div id="prompt_titleHelp" class="form-text">Name this prompt so it is easy to find later.</div>
                    </div>
                    <div class="mb-3">
                        <label for="text" class="form-label">Prompt Text</label>
                        <textarea class="form-control" id="text" aria-describedby="textHelp" rows="4"><?php echo ar_h($promptRow['text']); ?></textarea>
                        <div id="textHelp" class="form-text">Students will see this before they record.</div>
                    </div>
                    <div class="row g-3 m-0">
                        <div class="col-md-3 p-0 pe-md-2">
                            <label for="prepare_time" class="form-label">Prepare Time</label>
                            <input type="text" class="form-control" id="prepare_time" aria-describedby="prepare_timeHelp" value="<?php echo ar_h($promptRow['prepare_time']); ?>">
                            <div id="prepare_timeHelp" class="form-text">Seconds before recording.</div>
                        </div>
                        <div class="col-md-3 p-0 px-md-2">
                            <label for="response_time" class="form-label">Response Time</label>
                            <input type="text" class="form-control" id="response_time" aria-describedby="response_timeHelp" value="<?php echo ar_h($promptRow['response_time']); ?>">
                            <div id="response_timeHelp" class="form-text">Seconds to respond.</div>
                        </div>
                        <div class="col-md-6 p-0 ps-md-2 prompt-toggles">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="transcriptionReq" <?php echo $checked; ?>>
                                <label class="form-check-label" for="transcriptionReq">Allow or require students to transcribe their recording</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="readPromptAloud" <?php echo $readPromptChecked; ?>>
                                <label class="form-check-label" for="readPromptAloud">Read the prompt aloud before recording starts</label>
                            </div>
                        </div>
                    </div>
                </form>
                <div class='save-status mt-3' id="response"></div>
            </section>

            <section class="dashboard-card">
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">Student Responses</p>
                        <h2><?php echo $responseCount; ?> <?php echo $responseCount === 1 ? 'Response' : 'Responses'; ?></h2>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="../phpScripts/downloadPromptFiles.php?prompt_id=<?php echo urlencode($prompt_id); ?>&type=audio" class="btn btn-outline-primary btn-sm">Download All Audio</a>
                        <a href="../phpScripts/downloadPromptFiles.php?prompt_id=<?php echo urlencode($prompt_id); ?>&type=transcripts" class="btn btn-outline-primary btn-sm">Download All Transcriptions</a>
                    </div>
                </div>

                <?php if ($responseCount === 0) { ?>
                    <div class="empty-state">No students have submitted responses for this prompt yet.</div>
                <?php } ?>

                <?php foreach ($responses as $row) {
                    $studentName = ar_student_name($row);
                    $audioPath = ar_audio_file_path($row['filename']);
                    $transcription = trim((string) $row['transcription_text']);
                    ?>
                    <article class="response-card">
                        <div class='response-card-header'>
                            <div>
                                <h3><?php echo ar_h($studentName); ?></h3>
                                <p><?php echo ar_h($row['date_created']); ?></p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($audioPath) { ?>
                                    <a href="../phpScripts/downloadResponse.php?id=<?php echo urlencode($row['id']); ?>&type=audio" class="btn btn-outline-primary btn-sm">Download Audio</a>
                                <?php } ?>
                                <a href="../phpScripts/downloadResponse.php?id=<?php echo urlencode($row['id']); ?>&type=transcript" class="btn btn-outline-primary btn-sm">Download Transcription</a>
                            </div>
                        </div>
                        <div class='card-body'>
                            <?php if ($audioPath) { ?>
                                <audio class="audio-controls" controls>
                                    <source src='<?php echo ar_h("../" . ltrim($row['filename'], '/')); ?>' type='<?php echo ar_h($row['filetype']); ?>'>
                                </audio>
                            <?php } else { ?>
                                <div class="alert alert-warning mb-3">Audio file missing.</div>
                            <?php } ?>

                            <?php if ($transcription !== '') { ?>
                                <div class="transcript-block"><?php echo nl2br(ar_h($transcription)); ?></div>
                            <?php } else { ?>
                                <div class="transcript-empty">No transcription available.</div>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            </section>
        </div>

    </main>
    <footer class='p-2 bg-byu-navy text-white fixed-bottom'>
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-around">
                <div class="text-center small">
                    <div>Developed by Ben McMurry</div>
                    <div> <a href="https://elc.byu.edu">English Language Center</a>, <a href="https://www.byu.edu">BYU</a>
                    </div>

                </div>

            </div>
    </footer>
    <script src='../js/responses.js'></script>
  
</body>

</html>
