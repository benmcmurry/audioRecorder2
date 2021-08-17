<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');

$archiveStatus = $_POST['archiveStatus'];

if ($_POST['archiveStatus'] == "current") {
    $archiveStatus = 1;
} else {
    $archiveStatus = 0;
}
echo "Prompt " . $_POST['prompt_id'] . " has a status of $archiveStatus.";
$query = $elc_db->prepare("Update Prompts set archive=? where prompt_id=?");
$query->bind_param("ss", $archiveStatus, $_POST['prompt_id']);
$query->execute();
