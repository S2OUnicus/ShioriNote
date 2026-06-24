USE shiorinote;

-- v0.1.1: add site setting for enabling/disabling public user registration.
-- Existing value is preserved if the key already exists.
INSERT INTO site_settings(setting_key, setting_value, updated_at_utc)
VALUES ('registration_enabled', 'false', UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE setting_key = setting_key;
