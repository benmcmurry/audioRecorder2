ALTER TABLE Audio_files
    ADD COLUMN submission_id VARCHAR(64) DEFAULT NULL,
    ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'uploaded',
    ADD COLUMN transcription_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    ADD COLUMN transcription_error TEXT DEFAULT NULL,
    ADD COLUMN transcription_source VARCHAR(32) NOT NULL DEFAULT 'queue',
    ADD COLUMN processing_started_at DATETIME DEFAULT NULL,
    ADD COLUMN processing_finished_at DATETIME DEFAULT NULL;

CREATE UNIQUE INDEX uq_audio_files_submission_id ON Audio_files (submission_id);
CREATE INDEX idx_audio_files_status_created ON Audio_files (status, date_created);
CREATE INDEX idx_audio_files_processing_started_at ON Audio_files (processing_started_at);
