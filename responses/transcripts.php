<?php
$prompt_id = $_GET['prompt_id'];
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');

$query = $elc_db->prepare("Select * from Audio_files natural join Users where prompt_id=? order by date_created DESC");
$query->bind_param("s", $prompt_id);
$query->execute();
$result = $query->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="../css/style.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script type="text/javascript"></script>
    <style>

    </style>

</head>

<body>
    <header id="header" class="p-2 bg-byu-navy text-white fixed-top">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div id="title">
                    ELC Audio Recorder
                </div>
                <div id="user" class="text-end">
                    <?php echo $login; ?>
                </div>
            </div>

        </div>
    </header>
    <main>
        <div class="container mt-5 mb-5 pb-3">
            <div class="card m-0 p-0">
                <div class='card-body'>
                    <p class="card-text">
                        <?php
                        while ($row = $result->fetch_assoc()) {
                        ?>
                            <h4 class="card-text"><?php echo $row['name']; ?></h4>
                            <p class="card-text"><?php echo $row['transcription_text']; ?></p>
                        <?php   } ?></p>
                </div>
            </div>
        </div>


        </div>
    </main>
    <footer class='p-2 bg-byu-navy text-white fixed-bottom'>
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-around">
                <div class="text-center small">
                    <div>Developed by Ben McMurry</div>
                    <div> <a href="https://elc.byu.edu">English Language Center</a>, <a href="https://www.byu.edu">BYU</a>
                    </div>

                </div>

            </div>
    </footer>
