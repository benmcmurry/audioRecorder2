<?php
include_once('../../connectFiles/connect_ar.php');
if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
    // $ffmpeg = "/usr/local/bin/ffmpeg";
    $ffmpeg = "/opt/homebrew/bin/ffmpeg";
} else {
    $ffmpeg = "/usr/bin/ffmpeg";
}
define('SITE_ROOT', realpath(dirname(__FILE__)));

$targetdir = '/uploads/';
// name of the directory where the files should be stored
$time = date('Y-m-d-His');
$fileName = $_POST['name'];
$targetFile = SITE_ROOT . $targetdir . $_POST['name'] . $_POST['extension'];
echo $_FILES['myBlob']['tmp_name'];
$mp3File = SITE_ROOT . $targetdir . $_POST['name'] . "mp3";
$fileLocation = "uploads/".$fileName."mp3";
if (move_uploaded_file($_FILES['myBlob']['tmp_name'], $targetFile)) {
    shell_exec("$ffmpeg -i $targetFile $mp3File");
    shell_exec("rm $targetFile");

    // echo $output;
    echo "<p align='center'>Your response has been saved.</p>";
    if ($_POST['transcription'] == 1) {

        echo "<p>Now, please transcribe what you recorded. You can refer back to the audio above.</p>";
        echo "<div id='transcription1' contenteditable='true' class='transcription'></div>";
        echo "<a align='center' class='button saveTranscription' id='saveTranscription' onClick='saveTranscription({$_POST['prompt_id']}, \"{$_POST['netid']}\", 1)'>Save Transcription</a>";
        echo "<div id='saveStatus' class='saveStatus'></div>";
    }
} else {
    echo "There was an error. Please refresh and try again.";
}


$query = $elc_db->prepare("Insert into Audio_files (prompt_id, netid, filename, filesize, filetype, date_created) Values (?,?,?,?,?,now())");
$query->bind_param("sssss", $_POST['prompt_id'], $_POST['netid'], $fileLocation, $_FILES['myBlob']['size'], $_FILES['myBlob']['type']);
$query->execute();
$result = $query->get_result();
$last_id = $elc_db->insert_id;

// session_start();
