CREATE TABLE IF NOT EXISTS AudioRecorder_admins (
    netid VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (netid)
);

INSERT INTO AudioRecorder_admins (netid) VALUES ('blm39')
ON DUPLICATE KEY UPDATE netid = VALUES(netid);

CREATE TABLE IF NOT EXISTS Feedback_reports (
    id CHAR(36) NOT NULL,
    reporter_netid VARCHAR(255) NOT NULL,
    reporter_email VARCHAR(255) DEFAULT NULL,
    category VARCHAR(40) NOT NULL,
    description TEXT NOT NULL,
    page_url TEXT NOT NULL,
    page_title VARCHAR(255) NOT NULL DEFAULT '',
    viewport VARCHAR(64) NOT NULL DEFAULT '',
    user_agent VARCHAR(1000) NOT NULL DEFAULT '',
    screenshot_path VARCHAR(255) DEFAULT NULL,
    screenshot_sha256 CHAR(64) DEFAULT NULL,
    screenshot_bytes INT UNSIGNED DEFAULT NULL,
    status ENUM('open', 'in_review', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    admin_notes TEXT DEFAULT NULL,
    reviewed_by_netid VARCHAR(255) DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_status_created (status, created_at),
    KEY idx_feedback_reporter_created (reporter_netid, created_at),
    KEY idx_feedback_retention (status, resolved_at)
);
