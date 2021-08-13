<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}

if ($_SERVER['SERVER_NAME'] == 'localhost') {$server = $_SERVER['SERVER_NAME']."/~benmcmurry";} else {$server=" https://".$_SERVER['SERVER_NAME'];}

include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');
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
    <main role="main">
        <div class="container mt-5 mb-5">
        <?php
                    
                    $query = $elc_db->prepare("Select * from Prompts where netid=? and archive=0 order by date_created DESC");
                    $query->bind_param("s", $netid);
                    $query->execute();
                    $result = $query->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<div class='row promptList' id='".$row['prompt_id']."'>";
                        echo "<div><i class='bi-alarm'></i><strong>".$row['title']."</strong> <span class='small'>(last modified: ".$row['date_created'].")</span></div>";
                        echo "<div><a href='".$server."/audioRecorder/?prompt_id=".$row['prompt_id']."'>Student Link</a></div>";
                        echo "<div class='action_list'><a class='archive' data-promptId='".$row['prompt_id']."'>Archive</a><a class='responses' href='responses.php?prompt_id=".$row['prompt_id']."'>Edit and View Responses</a></div>";
                        echo "</div>";
                    }
                    
                ?>
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
</body>

</html>