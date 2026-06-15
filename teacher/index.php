<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('../phpScripts/responseHelpers.php');

$server = ar_public_origin() . ar_web_root() . '/index.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
    <script src="../js/accessibility-auto-alt.js" defer></script>
    <?php include_once __DIR__ . '/../includes/styles_and_scripts.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script type="text/javascript">


    </script>


</head>

<body>
    <?php include_once __DIR__ . '/../includes/site-header.php'; ?>
    <nav class="container mt-5 dashboard-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <p class="section-kicker">Teacher Dashboard</p>
                <h1 class="dashboard-title">Prompts</h1>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button id="createPrompt" class='btn btn-primary btn-sm' onclick='createPrompt()'>New Prompt</button>
                <button id="archiveToggleButton" class='btn btn-outline-primary btn-sm' onclick="archiveToggle();">Show Archived Prompts</button>
                <button id="multipleViewer" class='btn btn-outline-primary btn-sm' onclick="multipleViewer();">View Multiple Responses</button>
            </div>
        </div>
        <div class="row" id='response'></div>
    </nav>
    <main role="main" class="m-0 p-0">
        <div class="container-sm dashboard-shell mt-4 mb-5 pb-3">
            <?php

            $query = $elc_db->prepare("SELECT Prompts.prompt_id, Prompts.title, Prompts.text, Prompts.prepare_time, Prompts.response_time, Prompts.archive, Prompts.date_created, COUNT(Audio_files.id) AS response_count FROM Prompts LEFT JOIN Audio_files ON Prompts.prompt_id = Audio_files.prompt_id WHERE Prompts.netid=? GROUP BY Prompts.prompt_id, Prompts.title, Prompts.text, Prompts.prepare_time, Prompts.response_time, Prompts.archive, Prompts.date_created ORDER BY Prompts.date_created DESC");
            $query->bind_param("s", $netid);
            $query->execute();
            $result = $query->get_result();
            $promptCount = 0;
            while ($row = $result->fetch_assoc()) {
                $promptCount++;
                if ($row['archive'] == 0) {
                    $archiveIcon = "bi-archive";
                    $archiveTitle = "Archive Prompt";
                    $archiveStatus = "current";
                    $archiveHideClass = "";
                } else {
                    $archiveIcon = "bi-archive-fill";
                    $archiveTitle = "Un-Archive Prompt";
                    $archiveStatus = "archived";
                    $archiveHideClass = 'd-none';
                }
                $prompt_id = $row['prompt_id'];
            ?>
                <div class='promptList m-0 mb-3 p-0 <?php echo $archiveStatus . " " . $archiveHideClass; ?>' id='<?php echo ar_h($row['prompt_id']); ?>'>
                    <div class="card prompt-card m-0">
                        <div class='card-header prompt-toolbar d-flex flex-wrap justify-content-between align-items-start gap-2'>
                            <div>
                                <div class='prompt-title action-item'><?php echo ar_h($row['title'] ? $row['title'] : 'Untitled Prompt'); ?></div>
                                <div class="prompt-meta">
                                    <span class="badge bg-light text-dark border"><?php echo (int) $row['response_count']; ?> <?php echo (int) $row['response_count'] === 1 ? 'response' : 'responses'; ?></span>
                                    <span class="badge <?php echo $archiveStatus === 'archived' ? 'bg-secondary' : 'bg-success'; ?>"><?php echo $archiveStatus === 'archived' ? 'Archived' : 'Current'; ?></span>
                                    <span><?php echo ar_h($row['date_created']); ?></span>
                                </div>
                            </div>
                            <div class="btn-group toolbar-buttons">
                                <button id="link-<?php echo ar_h($prompt_id); ?>" class='btn btn-outline-primary action-item toolbar-button' title='Copy Student Link to Clipboard' aria-label='Copy Student Link to Clipboard' onClick="copyLink('<?php echo ar_h($prompt_id); ?>', '<?php echo ar_h($server); ?>');"><i class='bi bi-clipboard' aria-hidden='true'></i></button>
                                <button class='btn btn-outline-primary action-item toolbar-button' title='<?php echo ar_h($archiveTitle); ?>' aria-label='<?php echo ar_h($archiveTitle); ?>' onclick="archive('<?php echo ar_h($prompt_id); ?>', '<?php echo ar_h($archiveStatus); ?>')"><i id='icon-<?php echo ar_h($prompt_id); ?>' class='bi <?php echo ar_h($archiveIcon); ?>' aria-hidden='true'></i></button>
                                <div class='btn btn-outline-primary action-item toolbar-button'><input class="form-check-input" type="checkbox" value="<?php echo ar_h($prompt_id); ?>" id="flexCheckDefault-<?php echo ar_h($prompt_id); ?>" onclick='selectMultiple(this.value)'></div>
                                <label class="visually-hidden" for="flexCheckDefault-<?php echo ar_h($prompt_id); ?>">Select Prompt</label>
                            </div>
                        </div>

                        <div class='card-body prompt-information'>
                            <p class='card-text'>
                                You have <?php echo ar_h($row['prepare_time']); ?> seconds to prepare and <?php echo ar_h($row['response_time']); ?> seconds to respond.
                            </p>
                            <p class='card-text'>
                                <strong>Prompt:</strong> <?php echo ar_h($row['text']); ?>
                            </p>
                            <a class="btn btn-outline-primary btn-sm" href="../responses/index.php?prompt_id=<?php echo urlencode($row['prompt_id']); ?>">Edit Prompt/View Responses</a>

                        </div>
                    </div>
                </div>

            <?php
            }

            if ($promptCount === 0) {
                echo "<div class='empty-state'>No prompts have been created yet.</div>";
            }

            ?>
        </div>
    </main>
    <?php include_once __DIR__ . '/../includes/site-footer.php'; ?>
    <script src="../js/teacher.js"></script>

</body>

</html>
