<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}

if ($_SERVER['SERVER_NAME'] == 'localhost') {
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
    <style>
        .action-item {
            /* width: 3em;
           height: 3em; */
            /* border-radius: 2em;  */

            border: 0px;
            /* padding: .2em .5em; */
        }

        .bi {
            /* font-size: .3em; */
        }

        .promptList {
            background-color: #efefef;
            border-radius: 5px;
            padding: 1em;
        }

        .prompt-toolbar {
            margin: 0px;
            padding-right: 0;
            padding-left: 0;
        }

        .prompt-information {
            margin:0px;
            border: 1px solid black;
            border-radius: .5em;
            background-color: white;
        }

        .prompt-information p {
            padding-top: .5em;
            padding-bottom: .5em;
            padding-left: 0;
            padding-right: 0;
            margin: 0;
        }

        .prompt-title {
            font-size: 1rem;
            line-height: 1.5;
            font-weight: bold;

            padding: .375rem .75rem  .375rem 0rem;
        }

        .toolbar-button {
            padding: .375rem .375rem;
        }
    </style>

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
    <main role="main">
        <div class="container-sm mt-5 mb-5">
            <?php

            $query = $elc_db->prepare("Select * from Prompts where netid=? and archive=0 order by date_created DESC");
            $query->bind_param("s", $netid);
            $query->execute();
            $result = $query->get_result();
            while ($row = $result->fetch_assoc()) {
            ?>
                <div class='row promptList' id='<?php echo $row['prompt_id']; ?>'>
                    <div class='row prompt-toolbar justify-content-between'>
                        <div class='prompt-title action-item col-sm-auto text-nowrap'><?php echo $row['title']; ?></div>
                        <div class="btn-group col-sm-auto toolbar-buttons">
                            <button class='btn btn-outline-primary action-item toolbar-button ' title='Copy Student Link to Clipboard'><i class='bi bi-clipboard'></i></button>
                            <a class='btn btn-outline-primary action-item toolbar-button' role='button' title='Edit Prompt' href='responses.php?prompt_id=<?php echo $row[' prompt_id']; ?>'><i class='bi bi-pencil-square'></i></a>
                            <a class='btn btn-outline-primary action-item toolbar-button' role='button' title='View Responses' href='responses.php?prompt_id=<?php echo $row[' prompt_id']; ?>'><i class='bi bi-eye'></i></a>
                            <button class='btn btn-outline-primary action-item toolbar-button' title='Archive Prompt' data-promptId='<?php echo $row[' prompt_id'] ?>'><i class='bi bi-archive'></i></button>
                        </div>
                    </div>

                    <div class='row prompt-information' style='border: 1px solid black;'>
                        <p>You have <?php echo $row['prepare_time']; ?> seconds to prepare and <?php echo $row['response_time']; ?> seconds to respond.</p>
                        <p><Strong>Prompt: </strong> <?php echo $row['text']; ?>
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
</body>

</html>