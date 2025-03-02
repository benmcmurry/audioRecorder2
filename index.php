<?php
$alreadyDone = false;
$prompt_id = 0;
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}
include_once("cas-go.php");
include_once('../../connectFiles/connect_ar.php');
include_once('addUser.php');
if (isset($_GET['prompt_id'])) {$prompt_id = $_GET['prompt_id'];
$alreadyDone = FALSE;

$query = $elc_db->prepare("Select * from Prompts where prompt_id=?");
$query->bind_param("s", $prompt_id);
$query->execute();
$result = $query->get_result();
$result = $result->fetch_assoc();

$query2 = $elc_db->prepare("Select * from Audio_files where prompt_id=? and netid=?");
$query2->bind_param("ss", $prompt_id, $netid);
$query2->execute();
$result2 = $query2->get_result();
$result2 = $result2->fetch_assoc();
if (isset($result2)) {
    $alreadyDone = TRUE;
}
}
$query3 = $elc_db->prepare("SELECT * FROM Audio_files NATURAL JOIN Users JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.netid = ? ORDER BY Audio_files.date_created DESC");
$query3->bind_param("s", $netid);
$query3->execute();
$result3 = $query3->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script type="text/javascript">
        <?php if ($prompt_id) { ?>
            var prepare_time = <?php echo $result['prepare_time']; ?>;
            var response_time = <?php echo $result['response_time']; ?>;
            var prompt_id = <?php echo $prompt_id; ?>;
            var netid = "<?php echo $net_id; ?>";
            var archiveStatus = <?php echo $result['archive']; ?>;
            var promptText = "<?php echo $result['text']; ?>";
            <?php

        } else {
            ?>var prompt_id = 0;
            var archiveStatus = 2
        <?php }




        if ($alreadyDone) {
            echo "var alreadyDone =  $alreadyDone;";
            echo "var reviewSource = '" . $result2['filename'] . "';";
            echo "var reviewSourceType = '" . $result2['filetype'] . "';";
        } else {
            echo "var alreadyDone = false;";
        }
        echo "console.log(prompt_id);";
        ?>
    </script>
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
        <div id="mainContainer" class="container mt-5 mb-5 pb-3">



            <!-- AlreadyDone? -->
            <div id="alreadyDoneBox" class="d-none d-grid gap-2 col mx-auto mt-5">
                <div class="row" id="alreadyAnswered">
                <p class="text-center">You have already answered this prompt.</p>

                    <p class="text-center">You can play your answer below.</p>
                </div>
                <div class="row">
                    <audio class="" id="reviewRecording" controls>
                    </audio>
                </div>

                <div id="transcriptionRow" class="row">
                    <h4>Transcription</h4> 
                    <div class="">
                        <textarea class="form-control" id='transcriptionBox' placeholder="Please wait for your transcription . . . ." id="floatingTextarea"><?php
                            if(isset($result2['transcription_text'])) {echo $result2['transcription_text'];} 
                            ?></textarea>
                    </div>
                </div>
                <div class="row justify-content-end" id="response"></div>

                <div id="repeatRecording" class="row bg-danger d-none" style="color: white; padding:2em;">
                    <p class="text-center">Please enter the password to allow the student to re-record. Please be aware that any previous recordings will be deleted.</p>
                    <input id='repeatPassword' type='password'></input>
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
                echo "<p>" . $result['text'] . "<br /> <br />";
                echo "You have {$result['prepare_time']} seconds to prepare and {$result['response_time']} seconds to respond.</p>";
                ?>
            </div>

            <!-- prepare and record display     -->
            <div id="prepareAndRecord" class="row justify-content-center">
                <div id='timer_container' class="d-flex row flex-wrap align-items-center justify-content-between d-none">
                    <img id='timeOrRecord' class="col-2 oscillate" src='images/lightbulb.jpg' />
                    <div id='timer' class='col-10 text-end'></div>

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
        <div id="allRecordings"  style="display:none;" class="container mt-5 mb-5 pb-3">
        <nav class="container mt-5">
      
      <a id="createPrompt" class='btn btn-primary me-3' href='teacher/index.php'>Teacher Area</a></nav>
            <?php
            echo "<p> Recordings for $name. </p>";
            while ($row = $result3->fetch_assoc()) { ?>

                <div class="row">
                    <div class="card  m-0 p-0" id='<?php echo $row['prompt_id']; ?>'>
                        <div class='card-header'> <h5 style="margin:0;padding:0;"><?php echo $row['title']."</h5>".$row['date_created']; ?> </div>
                        <div class='card-body'> 
                            <?php
                            echo "<p class='card-text'><strong>Prompt: </strong>". $row['text']." </p><p>You have ".$row['prepare_time']." seconds to prepare and ".$row['response_time']." seconds to record.</p>";?>
                            <audio class="audio-control" style='padding: 0em 0em 2em;' controls>
                                <source src='<?php echo $row['filename']; ?>' type='<?php echo $row['filetype']; ?>'>
                            </audio> 
                            <?php 
                            if ($row['transcription_text']) {
                                echo "<p class='card-text'>Transcript: ".$row['transcription_text']." </p>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
       </div>
     
    </main>
    <footer class='p-2 bg-byu-navy text-white fixed-bottom'>
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-around">
                <div class="text-center small">
                    <div>Developed by Ben McMurry</div>
                    <div>
                        <a href="https://elc.byu.edu">English Language Center</a>, <a href="https://www.byu.edu">BYU</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
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