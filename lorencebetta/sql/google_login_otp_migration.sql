-- Apply manually in phpMyAdmin only after reviewing a database backup.
-- Do not execute this migration automatically during FTP deployment.
CREATE TABLE IF NOT EXISTS google_login_otps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    google_sub VARCHAR(255) NOT NULL,
    email VARCHAR(254) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    INDEX idx_google_email (email),
    INDEX idx_google_sub (google_sub),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Confirmed against the live users structure on 2026-10-09.\n-- Execute ONCE after exporting a database backup.\n-- Never link existing admin or password accounts by email alone.
ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE users ADD UNIQUE KEY uq_users_google_sub (google_sub);
