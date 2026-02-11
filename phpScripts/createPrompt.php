<?php

include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');

function ensureReadPromptColumn($db)
{
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    if ($columnCheck && $columnCheck->num_rows > 0) {
        return true;
    }

    $db->query("ALTER TABLE Prompts ADD COLUMN read_prompt TINYINT(1) NOT NULL DEFAULT 1");
    $columnCheck = $db->query("SHOW COLUMNS FROM Prompts LIKE 'read_prompt'");
    return $columnCheck && $columnCheck->num_rows > 0;
}

ensureReadPromptColumn($elc_db);

$query = $elc_db->prepare("Insert into Prompts (prompt_id, netid) values (null, ?)");
$query->bind_param("s", $netid);
$query->execute();
$result = $query->get_result();
$prompt_id = $query->insert_id;
echo "../responses/?prompt_id=".$prompt_id;
?>
