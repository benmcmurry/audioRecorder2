<?php
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
$prompt_id = $_POST['prompt_id'];
$title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
$text = htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8');
$prepare_time = $_POST['prepare_time'];
$response_time = $_POST['response_time'];
if($_POST['transcription'] == "true") {$transcription = 1;} else {$transcription = 0;}

$query = $elc_db->prepare("Update Prompts set title = ?, text = ?, prepare_time = ?, response_time = ?, transcription = ? where prompt_id = ?");
$query->bind_param("ssssss", $title, $text, $prepare_time, $response_time, $transcription, $prompt_id);
$query->execute();
$result = $query->get_result();
echo "Saved!"





?>