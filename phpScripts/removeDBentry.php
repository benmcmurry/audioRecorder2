<?php

include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');

$query = $elc_db->prepare("Delete from Audio_files where prompt_id=? and netid=?");
$query->bind_param("ss", $_POST['prompt_id'], $_POST['netid']);
$query->execute();
$result = $query->get_result();
$prompt_id = $query->insert_id;


echo "Refreshing . . . ";

?>