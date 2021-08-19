<?php

include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');

$query = $elc_db->prepare("Insert into Prompts (prompt_id, netid) values (null, ?)");
$query->bind_param("s", $netid);
$query->execute();
$result = $query->get_result();
$prompt_id = $query->insert_id;
echo "../responses/?prompt_id=".$prompt_id;
?>