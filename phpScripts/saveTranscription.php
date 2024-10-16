<?php
include_once('../../../connectFiles/connect_ar.php');
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);


$query = $elc_db->prepare("Update Audio_files set transcription_text = ? where prompt_id = ? and netid=?");
$query->bind_param("sss", $_POST['transcription'], $_POST['prompt_id'], $_POST['netid']);
$query->execute();
$result = $query->get_result();
$time = date('F jS\, Y h:i:s A');
// echo "Saved on $time";
echo "Saving . . .";
?>