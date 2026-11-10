# OpenAI API activity audit

Audio Recorder records one row for each attempt to use OpenAI speech generation or transcription. Retries are separate attempts; cached prompt audio creates no row. The log records the signed-in prompt owner's NetID for speech generation and the recording owner's NetID for background transcription.

The audit table stores the operation, model, timestamp, outcome, HTTP status or safe error code, duration, NetID, and related prompt or recording ID. It never stores prompt text, transcripts, audio, API keys, or raw request/response bodies. If the app cannot create the initial audit row, it does not send the OpenAI request. Rows whose result update cannot be saved remain marked `pending` so the attempt is visible for review.

Only users listed in `AudioRecorder_admins` can view `admin/api-activity.php`. The page shows the newest 100 entries first and supports filtering by date, NetID, operation, and outcome.

## Database migration

Apply the migration before relying on OpenAI calls in the updated application. The API wrappers fail closed if the audit table is unavailable.

```sh
php scripts/migrate_openai_activity.php --expected-db=audiorecorder
php scripts/migrate_openai_activity.php --expected-db=audiorecorder --apply
```

The first command is a dry run. Back up the production database before running the second command. The migration uses `CREATE TABLE IF NOT EXISTS` and is safe to rerun.

## Retention

Schedule `php scripts/purge_openai_activity.php` to run once daily under an account that can load the Audio Recorder private database configuration. The script is CLI-only and removes audit rows older than 90 days. It reports the number of deleted rows and exits with a failure status if the table or database is unavailable.
