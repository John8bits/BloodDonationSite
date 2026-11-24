-- Member Settings & Privacy security features.
-- Apply once after the existing member, clinic, and admin migrations.

ALTER TABLE uinfo
  ADD COLUMN member_deactivated_at DATETIME NULL DEFAULT NULL AFTER account_status;

CREATE TABLE member_privacy_preferences (
  user_id INT(10) UNSIGNED NOT NULL,
  show_first_name TINYINT(1) NOT NULL DEFAULT 0,
  show_blood_type TINYINT(1) NOT NULL DEFAULT 0,
  show_province TINYINT(1) NOT NULL DEFAULT 0,
  location_discovery_enabled TINYINT(1) NOT NULL DEFAULT 0,
  optional_privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
  privacy_consent_updated_at DATETIME NULL DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_member_privacy_preferences_user
    FOREIGN KEY (user_id) REFERENCES uinfo (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_sessions_hash (session_hash),
  KEY idx_member_sessions_user_active (user_id, revoked_at, last_seen_at),
  CONSTRAINT fk_member_sessions_user
    FOREIGN KEY (user_id) REFERENCES uinfo (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_email_change_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED NOT NULL,
  new_email VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_email_change_token (token_hash),
  UNIQUE KEY uq_member_email_change_email (new_email),
  KEY idx_member_email_change_user (user_id, expires_at),
  CONSTRAINT fk_member_email_change_user
    FOREIGN KEY (user_id) REFERENCES uinfo (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_account_deletion_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED NOT NULL,
  status ENUM('pending','approved','declined','cancelled') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL DEFAULT NULL,
  reviewed_by INT(10) UNSIGNED NULL DEFAULT NULL,
  review_note VARCHAR(500) NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_member_deletion_status (status, requested_at),
  KEY idx_member_deletion_user (user_id, status),
  CONSTRAINT fk_member_deletion_user
    FOREIGN KEY (user_id) REFERENCES uinfo (id) ON DELETE CASCADE,
  CONSTRAINT fk_member_deletion_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES uinfo (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    