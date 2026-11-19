-- Multi-prompt activities use private, ordered snapshots of Prompts rows.
-- Run once against the Audio Recorder database before enabling the feature.
CREATE TABLE IF NOT EXISTS Activities (
    activity_id CHAR(32) NOT NULL,
    netid VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    archive TINYINT(1) NOT NULL DEFAULT 0,
    date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (activity_id),
    KEY idx_activities_owner (netid, archive, date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Activity_items (
    item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id CHAR(32) NOT NULL,
    position INT UNSIGNED NOT NULL,
    prompt_id BIGINT UNSIGNED NOT NULL,
    source_prompt_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (item_id),
    UNIQUE KEY uq_activity_position (activity_id, position),
    UNIQUE KEY uq_activity_prompt (activity_id, prompt_id),
    KEY idx_activity_prompt (prompt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
