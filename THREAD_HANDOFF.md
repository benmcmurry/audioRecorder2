# Audio Recorder Queue Fix

## What changed
- Uploads now save immediately and enqueue transcription work in the background.
- The worker runs from `phpScripts/processPendingAudio.php` and updates `Audio_files` as it progresses.
- `OPENAI_API_KEY` and `FFMPEG_BINARY` are passed into the worker explicitly so the CLI child does not depend on Apache shell inheritance.
- Manual transcript edits still win because `saveTranscription.php` marks the row as manual/complete.

## What to remember
- Temporary debug output lives in `logs/audio_recorder_pipeline.log`, but the directory is ignored except for `.gitkeep`.
- The diagnostic script `phpScripts/diagnoseShellExec.php` was removed after the issue was resolved.
- If you need to troubleshoot the queue again, the log events to watch are `worker_start`, `worker_jobs_loaded`, `worker_job_claimed`, `worker_transcribe_start`, and `worker_transcribe_success` or `worker_transcribe_failed`.

