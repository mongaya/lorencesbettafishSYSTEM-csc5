-- Google sign-in / OTP database migration for the existing production database.
-- Run once in phpMyAdmin after confirming that users.google_sub does not already exist.
-- Back up the production database before running this migration.
-- Do not run database_v2.sql or upgrade.sql against production as a replacement.

ALTER TABLE users
  ADD COLUMN google_sub VARCHAR(255) NULL,
  ADD UNIQUE KEY uq_users_google_sub (google_sub);

CREATE TABLE IF NOT EXISTS google_login_otps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  google_sub VARCHAR(255) NOT NULL,
  email VARCHAR(150) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_google_otp_sub (google_sub),
  INDEX idx_google_otp_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
