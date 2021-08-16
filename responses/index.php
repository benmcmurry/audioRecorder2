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
        <div class="container-fluid mt-5">
            <div class='row'>
                <div class='col-6'>
                    <?php
                    $promptQuery = $elc_db->prepare("Select * from Prompts where prompt_id=?");
                    $promptQuery->bind_param("s", $prompt_id);
                    $promptQuery->execute();
                    $promptResult = $promptQuery->get_result();
                    $promptRow = $promptResult->fetch_assoc();
                    ?>
                    <div class="row">
                        <div class="col">
                            Title
                        </div>
                        <div class="col">
                            <div id='title' contenteditable='true' class='editable'><?php echo $promptRow['title']; ?></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            Prompt
                        </div>
                        <div class="col">
                            <div id='text' contenteditable='true' class='editable'><?php echo $promptRow['text']; ?></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            Preparation Time (in seconds)
                        </div>
                        <div class="col">
                            <div id='prepare_time' contenteditable='true' class='editable'><?php echo $promptRow['prepare_time']; ?></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            Response Time (in seconds)
                        </div>
                        <div class="col">
                            <div id='response_time' contenteditable='true' class='editable'><?php echo $promptRow['response_time']; ?></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            Are students required to transcribe their recording?
                        </div>
                        <div class="col">
                            <?php
                            if ($promptRow['transcription'] == 1) { ?>
                                Yes <input type='radio' value='1' name='transcriptionReq' checked> &nbsp; &nbsp; No <input type='radio' value='0' name='transcriptionReq'>
                            <?php } else { ?>
                                Yes <input type='radio' value='1' name='transcriptionReq'> &nbsp; &nbsp; No <input type='radio' value='0' name='transcriptionReq' checked>
                            <?php
                            } ?>
                        </div>
                        </div>



                        <div class='row'><a class='button' id='save'>Save</a></div>
                    </div>
                    <div class='col-6'>
                        <?php
                        $transcription_text = "<h2>Transcripts for copy and paste</h2>";
                        $query = $elc_db->prepare("Select * from Audio_files natural join Users where prompt_id=? order by date_created DESC");
                        $query->bind_param("s", $prompt_id);
                        $query->execute();
                        $result = $query->get_result();
                        while ($row = $result->fetch_assoc()) { ?>
                            <div class='response' id='<?php echo $row['prompt_id']; ?>'>
                                <div class='title'>
                                    <?php echo $row['name']; ?>
                                </div>
                                <div class='results'>
                                    <audio style='padding: 0em 0em 2em;' controls>
                                        <source src='<?php echo $row['filename']; ?>' type='<?php echo $row['filetype']; ?>'>
                                    </audio>
                                </div>
                                <div class='transcription'>
                                    <?php echo $row['transcription_text']; ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

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