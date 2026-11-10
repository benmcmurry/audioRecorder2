CREATE TABLE IF NOT EXISTS OpenAI_activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    netid VARCHAR(255) DEFAULT NULL,
    operation ENUM('speech_generation', 'transcription') NOT NULL,
    model VARCHAR(120) NOT NULL,
    outcome ENUM('pending', 'succeeded', 'failed') NOT NULL DEFAULT 'pending',
    http_status SMALLINT UNSIGNED DEFAULT NULL,
    error_code VARCHAR(100) DEFAULT NULL,
    error_type VARCHAR(100) DEFAULT NULL,
    duration_ms INT UNSIGNED DEFAULT NULL,
    related_type ENUM('prompt', 'recording') NOT NULL,
    related_id VARCHAR(64) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_openai_activity_attempted (attempted_at, id),
    KEY idx_openai_activity_netid_attempted (netid, attempted_at),
    KEY idx_openai_activity_operation_outcome_attempted (operation, outcome, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
