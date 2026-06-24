-- Default site settings for ShioriNote.
-- This file does not create an initial user.
-- The initial administrator is created by /install/.

INSERT INTO site_settings(setting_key, setting_value) VALUES
('site_name','しおりノート'),
('author','ShioriNote Project'),
('theme_color','#6E1E51'),
('description_default','図書ごと・ユーザーごとの読書進展を、目次・メモ・グラフでやさしく管理するサイトです。'),
('default_timezone','Asia/Tokyo'),
('rewrite_enabled','true'),
('registration_enabled','false'),
('progress_time_bucket','daily')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at_utc = UTC_TIMESTAMP(6);
