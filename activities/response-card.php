<?php
$audioPath = ar_audio_file_path($response['filename']);
$transcription = trim((string) $response['transcription_text']);
$pending = $transcription === '' && in_array((string) $response['transcription_status'], array('pending', 'processing'), true);
?>
<article class="response-card">
    <div class="response-card-header">
        <div><h4 class="h6"><?php echo ar_h(ar_student_name($response)); ?></h4><p><?php echo ar_h($response['date_created']); ?></p></div>
        <a class="btn btn-outline-primary btn-sm" href="../phpScripts/downloadResponse.php?id=<?php echo (int) $response['response_id']; ?>&type=audio">Download audio</a>
    </div>
    <div class="card-body">
        <?php if ($audioPath) { ?>
            <audio class="audio-controls" controls preload="none" aria-label="Prompt <?php echo (int) $response['position']; ?> response from <?php echo ar_h(ar_student_name($response)); ?>">
                <source src="../phpScripts/streamResponse.php?id=<?php echo (int) $response['response_id']; ?>" type="<?php echo ar_h($response['filetype']); ?>">
            </audio>
        <?php } else { ?><div class="alert alert-warning mb-2">Audio file missing.</div><?php } ?>
        <?php if ($transcription !== '') { ?><div class="transcript-block"><?php echo nl2br(ar_h($transcription)); ?></div>
        <?php } elseif ($pending) { ?><div class="transcript-empty">Transcription is still processing.</div>
        <?php } else { ?><div class="transcript-empty">No transcription available.</div><?php } ?>
    </div>
</article>
