<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", 1);
include_once("../cas-go.php");
include_once((getenv('APP_PRIVATE_ROOT') ? rtrim(trim((string) getenv('APP_PRIVATE_ROOT')), '/') : dirname(__DIR__, 3) . '/private-config') . '/connectFiles/connect_ar.php');
include_once('../addUser.php');
include_once('../phpScripts/responseHelpers.php');

$appRoot = ar_web_root();
$prompt_id = isset($_GET['prompt_id']) ? trim((string) $_GET['prompt_id']) : '';

$query = $elc_db->prepare("SELECT Audio_files.*, Users.name AS user_name FROM Audio_files LEFT JOIN Users ON Audio_files.netid = Users.netid WHERE Audio_files.prompt_id=? ORDER BY Audio_files.date_created DESC");
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
    <script src="../js/accessibility-auto-alt.js" defer></script>
    <?php include_once __DIR__ . '/../includes/styles_and_scripts.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/shared-shell.php'; audio_recorder_render_header(); ?>

    <main class="container dashboard-shell mt-4 mb-5 pb-3">
        <section class="dashboard-card mb-4">
            <div class="section-heading">
                <div>
                    <p class="section-kicker">Transcripts</p>
                    <h1 class="dashboard-title">Prompt transcript list</h1>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="<?php echo $appRoot; ?>/teacher/">Return to Prompt List</a>
            </div>
        </section>

        <section class="dashboard-card">
            <?php if (!$prompt_id) { ?>
                <div class="empty-state">No prompt ID was provided.</div>
            <?php } else { ?>
                <div class="card m-0 p-0 border-0">
                    <div class="card-body">
                        <?php
                        while ($row = $result->fetch_assoc()) {
                            $transcription = trim((string) $row['transcription_text']);
                            $isTranscriptionPending = empty($transcription) && in_array((string) ($row['transcription_status'] ?? ''), array('pending', 'processing'), true);
                            ?>
                            <h4 class="card-text"><?php echo ar_h($row['user_name'] ? $row['user_name'] : $row['netid']); ?></h4>
                            <p class="card-text"><?php echo $isTranscriptionPending ? 'Transcription is still processing.' : ar_h($row['transcription_text']); ?></p>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </section>
    </main>

    <?php audio_recorder_render_footer(); ?>
</body>
</html>
