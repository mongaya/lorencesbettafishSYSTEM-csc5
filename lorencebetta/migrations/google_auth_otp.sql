-- IMPORTANT: HISTORICAL BACKUP ALREADY HAS GOOGLE AUTH SCHEMA.
-- The user's uploaded SQL export includes users.google_sub, its unique index,
-- and google_login_otps. DO NOT run an ALTER TABLE migration blindly.
-- Read-only checks to run in production phpMyAdmin (SQL tab):
SHOW COLUMNS FROM users LIKE 'google_sub';
SHOW INDEX FROM users WHERE Key_name = 'uq_users_google_sub';
SHOW TABLES LIKE 'google_login_otps';
SHOW COLUMNS FROM google_login_otps;
-- If these return the expected fields, NO MIGRATION IS NEEDED.
-- If any are missing, inspect the actual live schema and take a fresh DB
-- backup before applying a tailored migration. Do not import the old export.
