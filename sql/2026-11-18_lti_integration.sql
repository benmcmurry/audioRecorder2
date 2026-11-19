CREATE TABLE IF NOT EXISTS Lti_identities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issuer VARCHAR(255) NOT NULL,
    deployment_id VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    netid VARCHAR(255) NOT NULL,
    identity_hash CHAR(64) NOT NULL,
    display_name VARCHAR(200) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lti_identity_hash (identity_hash),
    KEY idx_lti_netid (netid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Lti_resources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_key_hash CHAR(64) NOT NULL,
    issuer VARCHAR(255) NOT NULL,
    deployment_id VARCHAR(255) NOT NULL,
    owner_netid VARCHAR(255) NOT NULL,
    target_type ENUM('prompt', 'activity') NOT NULL,
    target_id VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lti_resource_key_hash (resource_key_hash),
    KEY idx_lti_resource_owner (owner_netid, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Lti_submission_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    issuer VARCHAR(255) NOT NULL,
    deployment_id VARCHAR(255) NOT NULL,
    context_id VARCHAR(255) NOT NULL DEFAULT '',
    resource_link_id VARCHAR(255) NOT NULL DEFAULT '',
    netid VARCHAR(255) NOT NULL,
    target_type ENUM('prompt', 'activity') NOT NULL,
    target_id VARCHAR(64) NOT NULL,
    response_ids TEXT NOT NULL,
    submission_scope_hash CHAR(64) NOT NULL,
    lineitem_url TEXT NOT NULL,
    delivery_status ENUM('pending', 'sent', 'failed', 'not_applicable') NOT NULL DEFAULT 'pending',
    delivery_error VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lti_submission_token_hash (token_hash),
    UNIQUE KEY uq_lti_submission_scope_hash (submission_scope_hash),
    KEY idx_lti_submission_owner (netid, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
