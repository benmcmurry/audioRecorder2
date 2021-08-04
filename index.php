<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}
include_once("cas-go.php");
include_once('../../connectFiles/connect_ar.php');
include_once('addUser.php');
$prompt_id = $_GET['prompt_id'];

$query = $elc_db->prepare("Select * from Prompts where prompt_id=?");
$query->bind_param("s", $prompt_id);
$query->execute();
$result = $query->get_result();
$result = $result->fetch_assoc();

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
        var prepare_time = <?php echo $result['prepare_time']; ?>;
        var response_time = <?php echo $result['response_time']; ?>;
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
        <div class="container mt-5 mb-5">
            <div id="buttons" class="d-grid gap-2 col mx-auto mt-5">
                <button id="testButton" type="button" class="btn btn-success" onclick="testStartRecording();">Test Microphone</button>
                <button type="button" class="btn btn-primary" onclick="startRecording();">Begin Recording</button>
            </div>

            <div id="prompt" class="d-none">
                <?php
                echo "<p>" . $result['text'] . "<br /> <br />";
                echo "You have {$result['prepare_time']} seconds to prepare and {$result['response_time']} seconds to respond.</p>";
                ?>
            </div>
            <div class="row justify-content-center">
                <div id='timer_container' style="width: 300px" class="d-flex row flex-wrap align-items-center justify-content-between d-none">
                    <img id='type' class="col-2" src='images/lightbulb.jpg' />
                    <div id='timer' class='col-10 text-end'></div>

                </div>
            </div>

            <div class="row justify-content-center">
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
            <audio id="live" muted></audio>
            <audio id="playback" autoplay playsinline></audio>

            <script src="js/main.js"></script>
        </div> <!-- end container -->
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