# Audio Recorder feedback reports

The shared cougar feedback widget submits to `feedback-submit.php`. Reports are stored in the Audio Recorder database and reviewed at `admin/feedback.php`. Signed-in users may submit reports; only NetIDs in `AudioRecorder_admins` can view or update them. The initial migration grants that role to `blm39`.

## Database setup

The migration creates the report and reviewer tables and seeds the initial reviewer. To apply it on production, copy the updated code to the server, back up the Audio Recorder database, then run a dry run and apply from the repository root:

```sh
php scripts/migrate_feedback_reports.php --expected-db=audiorecorder
php scripts/migrate_feedback_reports.php --expected-db=audiorecorder --apply
```

The script is CLI-only and refuses to run unless the connected database name matches `--expected-db`. Without `--apply`, it makes no database changes. The SQL uses `IF NOT EXISTS` and an idempotent reviewer seed, so it is safe to rerun if a previous attempt partially completed. Set `APP_PRIVATE_ROOT` if the production private configuration directory is not at the default sibling path.

## Screenshot storage

Screenshots are optional and are stored outside the site document root. By default, the site uses `private-storage/audio-recorder-feedback` beside the `Web` directory. Set `AR_FEEDBACK_STORAGE_ROOT` to an absolute private directory to change that location. Create the directory before deployment and grant the web-server account write access. The app creates dated directories with mode `0700` and image files with mode `0600`.

The endpoint accepts PNG screenshots up to 4 MB and 5000 pixels in either dimension. Reviewers view an attached image through the admin-only `admin/feedback-screenshot.php` endpoint.

## Retention

Run `php scripts/purge_feedback_attachments.php` daily. It removes screenshot files for reports that have been resolved or dismissed for more than 90 days. Report text and status remain available in the review queue.

After deployment, submit a test report with and without an optional screenshot, confirm it appears in the review queue, and verify another signed-in account cannot open the queue unless its NetID is in `AudioRecorder_admins`.
