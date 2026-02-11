<?php
$prompt_id = $_GET['prompt_id'];
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
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
    <nav class="container mt-5">
        <div class="row">
        <a id="createPrompt" class='button btn btn-primary me-3' href="../teacher/">Return to Prompt list</a>
</div>
<?php
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $promptUrl = $scheme . '://' . $host . ar_web_root() . '/index.php?prompt_id=' . urlencode($prompt_id);
?>
<div class="row mt-3">
    <div class="col">
        <label for="promptLink" class="form-label">Prompt Link</label>
        <div class="input-group">
            <input id="promptLink" type="text" class="form-control" readonly value="<?php echo htmlspecialchars($promptUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($promptUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open Prompt</a>
        </div>
    </div>
</div>
</nav>
    <main role="main">
        <div class="container mt-5 mb-5 pb-3">
            <!-- <div class='row'>
                <div class='col'> -->
            <?php
            $promptQuery = $elc_db->prepare("Select * from Prompts where prompt_id=?");
            $promptQuery->bind_param("s", $prompt_id);
            $promptQuery->execute();
            $promptResult = $promptQuery->get_result();
            $promptRow = $promptResult->fetch_assoc();
            if ($promptRow['transcription'] == 1) {
                $checked = "checked";
            } else {
                $checked = "";
            }
            if (!isset($promptRow['read_prompt']) || $promptRow['read_prompt'] == 1) {
                $readPromptChecked = "checked";
            } else {
                $readPromptChecked = "";
            }
            ?>

            <form id="updateForm">
                <div class="row">
                    <div class="mb-3">
                        <label for="prompt_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="prompt_title" aria-describedby="prompt_titleHelp" value="<?php echo $promptRow['title']; ?>">
                        <div id="prompt_titleHelp" class="form-text">Please enter a title for this prompt</div>
                    </div>
                </div>
                <div class="row">
                    <div class="mb-3">
                        <label for="text" class="form-label">Prompt Text</label>
                        <input type="text" class="form-control" id="text" aria-describedby="textHelp" value="<?php echo $promptRow['text']; ?>">
                        <div id="textHelp" class="form-text">Please write your prompt here.</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col mb-3">
                        <label for="prepare_time" class="form-label">Prepare Time</label>
                        <input type="text" class="form-control" id="prepare_time" aria-describedby="prepare_timeHelp" value="<?php echo $promptRow['prepare_time']; ?>">
                        <div id="prepare_timeHelp" class="form-text">Please enter a title for this prompt</div>
                    </div>

                    <div class="col mb-3">
                        <label for="response_time" class="form-label">Response Time</label>
                        <input type="text" class="form-control" id="response_time" aria-describedby="response_timeHelp" value="<?php echo $promptRow['response_time']; ?>">
                        <div id="response_timeHelp" class="form-text">Please enter a title for this prompt</div>
                    </div>

                    <div class="col form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="transcriptionReq" <?php echo $checked; ?>>
                        <label class="form-check-label" for="transcriptionReq">
                            Allow or Require Students to Transcribe their recording
                        </label>
                    </div>
                    <div class="col form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="readPromptAloud" <?php echo $readPromptChecked; ?>>
                        <label class="form-check-label" for="readPromptAloud">
                            Read the prompt aloud before recording starts
                        </label>
                    </div>
                    <div class="">
                        
                    </div>
                </div>

            </form>





            <div class='row'>
                <button class='btn btn-primary' id='save' onclick="save('<?php echo $prompt_id; ?>');">Save</button>
            </div>
            <div class='row'>
            <a href="transcripts.php?prompt_id=<?php echo $prompt_id; ?>" target="_blank" id="copyAllTranscripts" class="btn btn-primary">View all Transcripts</a>
            </div>
            <div class='row' id="response">

            </div>

            <?php
            $transcription_text = "<h2>Transcripts</h2>";
            $query = $elc_db->prepare("Select * from Audio_files natural join Users where prompt_id=? order by Users.name ASC");
            $query->bind_param("s", $prompt_id);
            $query->execute();
            $result = $query->get_result();

            while ($row = $result->fetch_assoc()) { 
                $transcription_text = "<h3>".$row['name']."</h3><p>".$row['transcription_text']."</p>";
                ?>
                <div class='row'>
                    <div class="card  m-0 p-0" id='<?php echo $row['prompt_id']; ?>'>
                        <div class='card-header'>
                            <?php echo $row['name']; ?>
                        </div>
                        <div class='card-body'>
                            <audio style='padding: 0em 0em 2em;' controls>
                                <source src='<?php echo "../".$row['filename']; ?>' type='<?php echo $row['filetype']; ?>'>
                            </audio>

                            <p class="card-text">
                                <?php echo $row['transcription_text']; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php

            } ?>
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
