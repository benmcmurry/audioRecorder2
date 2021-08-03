<?php
if($name=="") {$name='localhost';}
$query = $elc_db->prepare("Insert into Users (netid, name) values (?, ?) on duplicate key update name = VALUES(name)");
$query->bind_param("ss", $netid, $name);
$query->execute();
$result = $query->get_result();
?>