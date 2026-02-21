<?php
$prompts = $_GET['prompts'];
include_once("../cas-go.php");
include_once('../../../connectFiles/connect_ar.php');
include_once('../addUser.php');
$promptCount = substr_count($prompts, ",") + 1;
$promptList = explode(",", $prompts);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
    <script src="../js/accessibility-auto-alt.js" defer></script>
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
    <nav class="container mt-5">
        <div class="row">
        <a id="createPrompt" class='button btn btn-primary me-3' href="../teacher/">Return to Prompt list</a>
</div>
</nav>
    <main role="main">
        <div class="container mt-5 mb-5 pb-3">
        <?php 
      
        switch($promptCount) {
            case 1:
                $query = $elc_db->prepare("SELECT * FROM Audio_files NATURAL JOIN Users JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? ORDER BY name ASC;");
                $query->bind_param("s", $promptList[0]);
                break;
             case 2:
                $query = $elc_db->prepare("SELECT * FROM Audio_files NATURAL JOIN Users JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY name ASC;");
                $query->bind_param("ss", $promptList[0], $promptList[1]);
                break;
            case 3:
                $query = $elc_db->prepare("SELECT * FROM Audio_files NATURAL JOIN Users JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY name ASC;");
                $query->bind_param("sss", $promptList[0], $promptList[1], $promptList[2]);
                break;
            case 4:
                $query = $elc_db->prepare("SELECT * FROM Audio_files NATURAL JOIN Users JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY name ASC;");
                $query->bind_param("ssss", $promptList[0], $promptList[1], $promptList[2], $promptList[3]);
                break;
            case 5:
                $query = $elc_db->prepare("SELECT * FROM Audio_files NATURAL JOIN Users JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY name ASC;");
                $query->bind_param("sssss", $promptList[0], $promptList[1], $promptList[2], $promptList[3], $promptList[4]);
                break;
            case 6:
                $query = $elc_db->prepare("SELECT * FROM Audio_files NATURAL JOIN Users JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY name ASC;");
                $query->bind_param("ssssss", $promptList[0], $promptList[1], $promptList[2], $promptList[3], $promptList[4], $promptList[5]);
                break;




        }
        ?>

            <?php
            $transcription_text = "<h2>Transcripts</h2>";
            // $query = $elc_db->prepare("Select * from Audio_files natural join Users where prompt_id = ? order by name ASC");
            // $query->bind_param("s", $prompts);
            $query->execute();
            $result = $query->get_result();
            $previousName = "none";
            while ($row = $result->fetch_assoc()) { 
                $transcription_text = "<h3>".$row['name']."</h3><p>".$row['transcription_text']."</p>";
                ?>
                <?php if($row['name'] != $previousName && $previousName !="none") { 

                    ?>
                            </div> <!-- end card body -->
                        </div> <!-- end card -->
                    </div> <!-- end row -->
                <?php } // check to see if same as previous
                if($row['name'] != $previousName || $previousName == "none") { ?>   
                    <div class='row'>
                         <div class="card  m-0 p-0" id='<?php echo $row['prompt_id']; ?>'>
                            <div class='card-header'> <?php echo $row['name']; ?> </div>
                            <div class='card-body'>
                <?php } ?>
                    
                                <?php echo "<p>".$row['title']." (".$row['prepare_time']."/".$row['response_time'].") - ". $row['text']."</p>"; ?>
                                <audio class="audio-controls" style='padding: 0em 0em 2em;' controls>
                                    <source src='<?php echo "../".$row['filename']; ?>' type='<?php echo $row['filetype']; ?>'>
                                </audio>
                                <p class="card-text"> <?php echo $row['transcription_text']; ?> </p>
            <?php
                $previousName = $row['name'];
            } ?>
                            </div> <!-- end card body -->
                        </div> <!-- end card -->
                    </div> <!-- end row -->
        </div> <!-- end div inside of main -->
       
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
    <script src='../js/responses.js'></script>
  
</body>

</html>
