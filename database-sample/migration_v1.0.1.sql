USE shiorinote;

-- v1.0.1: rename Japanese site name to しおりノート.
INSERT INTO site_settings(setting_key, setting_value) VALUES
('site_name','しおりノート')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at_utc = UTC_TIMESTAMP(6);
