<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}

if ($_SERVER['SERVER_NAME'] == 'localhost.byu.edu') {
    $server = $_SERVER['SERVER_NAME'] . "/~benmcmurry";
} else {
    $server = " https://" . $_SERVER['SERVER_NAME'];
}

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


</head>

<body>
    <header id="header" class="p-2 bg-byu-navy text-white fixed-top">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div id="title">
                    ELC Audio Recorder
                </div>
                <div id="user" class="text-end">
                    <?php echo $login; ?>
                </div>
            </div>

        </div>
    </header>
    <nav class="container mt-5">
      
                <button id="createPrompt" class='btn btn-primary me-3' onclick='createPrompt()'>New Prompt</button>
   
                <button id="archiveToggleButton" class='btn btn-primary' onclick="archiveToggle();">Show Archived Prompts</button>
          
        <div class="row" id='response'></div>
    </nav>
    <main role="main" class="m-0 p-0">
        <div class="container-sm mt-5 mb-5 pb-3">
            <?php

            $query = $elc_db->prepare("Select * from Prompts where netid=? order by date_created DESC");
            $query->bind_param("s", $netid);
            $query->execute();
            $result = $query->get_result();
            while ($row = $result->fetch_assoc()) {
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
                <div class='row promptList m-0 mb-4 p-0 <?php echo $archiveStatus . " " . $archiveHideClass; ?>' id='<?php echo $row['prompt_id']; ?>'>
                    <div class="card  m-0 p-0">
                        <div class='card-header row prompt-toolbar justify-content-between p-0 m-0'>
                            <div class='prompt-title action-item col-sm-auto text-nowrap'><?php echo $row['title']; ?></div>
                            <div class="btn-group col-sm-auto toolbar-buttons">
                                <button class='btn btn-outline-primary action-item toolbar-button ' title='Copy Student Link to Clipboard' onClick="copyLink('<?php echo $prompt_id; ?>', '<?php echo $server; ?>');"><i class='bi bi-clipboard'></i></button>
                                <a class='btn btn-outline-primary action-item toolbar-button' role='button' title='Edit Prompt' href='../responses/index.php?prompt_id=<?php echo $row['prompt_id']; ?>'><i class='bi bi-pencil-square'></i></a>
                                <a class='btn btn-outline-primary action-item toolbar-button' role='button' title='View Responses' href='../responses/index.php?prompt_id=<?php echo $row['prompt_id']; ?>'><i class='bi bi-eye'></i></a>
                                <button class='btn btn-outline-primary action-item toolbar-button' title='<?php echo $archiveTitle; ?>' onclick="archive('<?php echo $prompt_id; ?>', '<?php echo $archiveStatus; ?>')"><i id='icon-<?php echo $prompt_id; ?>' class='bi <?php echo $archiveIcon; ?>'></i></button>
                            </div>
                        </div>

                        <div class='card-body prompt-information'>
                            <p class='card-text'>
                                You have <?php echo $row['prepare_time']; ?> seconds to prepare and <?php echo $row['response_time']; ?> seconds to respond.
                            </p>
                            <p class='card-text'>
                                <Strong>Prompt: </strong> <?php echo $row['text']; ?>
                            </p>
                            <p id="link-<?php echo $prompt_id; ?>" class='card-text'><?php echo "Student Link: <a href='$server/audioRecorder/?prompt_id=$prompt_id'>$server/audioRecorder/?prompt_id=$prompt_id</a>"; ?></p>

                        </div>
                    </div>
                </div>

            <?php
            }

            ?>
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
    <script src="../js/teacher.js"></script>

</body>

</html>