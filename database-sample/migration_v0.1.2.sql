USE shiorinote;

-- v0.1.2: rename default site identity to ShioriNote / 栞ノート.
-- This migration changes only site setting values. It does not change table structure.
INSERT INTO site_settings(setting_key, setting_value) VALUES
('site_name','栞ノート'),
('author','ShioriNote Project'),
('description_default','図書ごと・ユーザーごとの読書進展を、目次・メモ・グラフでやさしく管理するサイトです。')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at_utc = UTC_TIMESTAMP(6);
