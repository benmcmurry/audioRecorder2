<?php

include_once("../cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');

$query = $elc_db->prepare("Delete from Audio_files where prompt_id=? and netid=?");
$query->bind_param("ss", $_POST['prompt_id'], $_POST['netid']);
$query->execute();
$result = $query->get_result();
$prompt_id = $query->insert_id;


echo "Please refresh this page to re-record your response.";

?>
