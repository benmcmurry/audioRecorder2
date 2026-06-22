<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("../cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');

$archiveStatus = $_POST['archiveStatus'];

if ($_POST['archiveStatus'] == "current") {
    $archiveStatus = 1;
} else {
    $archiveStatus = 0;
}
$query = $elc_db->prepare("Update Prompts set archive=? where prompt_id=?");
$query->bind_param("ss", $archiveStatus, $_POST['prompt_id']);
$query->execute();
