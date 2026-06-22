<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("../cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('../phpScripts/responseHelpers.php');

$appRoot = ar_web_root();
$prompts = isset($_GET['prompts']) ? trim((string) $_GET['prompts']) : '';
$promptList = array_values(array_filter(array_map('trim', explode(',', $prompts)), 'strlen'));
$promptList = array_slice($promptList, 0, 6);
$promptCount = count($promptList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>ELC Audio Recorder</title>
    <script src="../js/accessibility-auto-alt.js" defer></script>
    <?php include_once __DIR__ . '/../includes/styles_and_scripts.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/shared-shell.php'; audio_recorder_render_header(); ?>

    <main role="main" class="container dashboard-shell mt-4 mb-5 pb-3">
        <section class="dashboard-card mb-4">
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Responses</p>
                    <h1 class="dashboard-title">Prompt response groups</h1>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="<?php echo $appRoot; ?>/teacher/">Return to Prompt List</a>
            </div>
            <p class="mb-0">Showing responses for <?php echo (int) $promptCount; ?> prompt<?php echo $promptCount === 1 ? '' : 's'; ?>.</p>
        </section>

        <section class="dashboard-card">
            <?php if ($promptCount === 0) { ?>
                <div class="empty-state">No prompt IDs were provided.</div>
            <?php } else { ?>
                <?php
                switch ($promptCount) {
                    case 1:
                        $query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name, Prompts.title, Prompts.prepare_time, Prompts.response_time, Prompts.text FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC;");
                        $query->bind_param("s", $promptList[0]);
                        break;
                    case 2:
                        $query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name, Prompts.title, Prompts.prepare_time, Prompts.response_time, Prompts.text FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC;");
                        $query->bind_param("ss", $promptList[0], $promptList[1]);
                        break;
                    case 3:
                        $query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name, Prompts.title, Prompts.prepare_time, Prompts.response_time, Prompts.text FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC;");
                        $query->bind_param("sss", $promptList[0], $promptList[1], $promptList[2]);
                        break;
                    case 4:
                        $query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name, Prompts.title, Prompts.prepare_time, Prompts.response_time, Prompts.text FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC;");
                        $query->bind_param("ssss", $promptList[0], $promptList[1], $promptList[2], $promptList[3]);
                        break;
                    case 5:
                        $query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name, Prompts.title, Prompts.prepare_time, Prompts.response_time, Prompts.text FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC;");
                        $query->bind_param("sssss", $promptList[0], $promptList[1], $promptList[2], $promptList[3], $promptList[4]);
                        break;
                    default:
                        $query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name, Prompts.title, Prompts.prepare_time, Prompts.response_time, Prompts.text FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid JOIN Prompts ON Audio_files.prompt_id = Prompts.prompt_id WHERE Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? OR Audio_files.prompt_id = ? ORDER BY COALESCE(Users.name, Audio_files.netid) ASC;");
                        $query->bind_param("ssssss", $promptList[0], $promptList[1], $promptList[2], $promptList[3], $promptList[4], $promptList[5]);
                        break;
                }

                $query->execute();
                $result = $query->get_result();
                $previousName = 'none';
                while ($row = $result->fetch_assoc()) {
                    $studentName = $row['user_name'] ? $row['user_name'] : $row['netid'];
                    if ($studentName != $previousName && $previousName !== 'none') {
                        ?>
                            </div>
                        </div>
                    </div>
                        <?php
                    }
                    if ($studentName != $previousName || $previousName === 'none') {
                        ?>
                        <div class="row mb-3">
                            <div class="card m-0 p-0">
                                <div class="card-header"><?php echo ar_h($studentName); ?></div>
                                <div class="card-body">
                        <?php
                    }
                    ?>
                                    <p><?php echo ar_h($row['title']); ?> (<?php echo ar_h($row['prepare_time']); ?>/<?php echo ar_h($row['response_time']); ?>) - <?php echo ar_h($row['text']); ?></p>
                                    <audio class="audio-controls" style="padding: 0 0 2em;" controls>
                                        <source src="<?php echo '../' . ar_h($row['filename']); ?>" type="<?php echo ar_h($row['filetype']); ?>">
                                    </audio>
                                    <p class="card-text"><?php echo ar_h($row['transcription_text']); ?></p>
                    <?php
                    $previousName = $studentName;
                }
                if ($previousName !== 'none') {
                    ?>
                                </div>
                            </div>
                        </div>
                    <?php
                }
                ?>
            <?php } ?>
        </section>
    </main>

    <?php audio_recorder_render_footer(); ?>
    <script src="../js/responses.js"></script>
</body>
</html>
