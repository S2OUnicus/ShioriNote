-- ShioriNote v2.3.0 migration
-- Adds per-book mindmap detail and chapter completion chart style settings.
-- This file avoids MySQL-incompatible "ADD COLUMN IF NOT EXISTS" syntax.
-- When using table prefixes, run this file through tool/upgrade/index.php.

SET @sn_books_table = 'books';

SET @sn_has_mindmap_depth = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @sn_books_table
      AND COLUMN_NAME = 'mindmap_depth'
);
SET @sn_sql = IF(
    @sn_has_mindmap_depth = 0,
    'ALTER TABLE `books` ADD COLUMN `mindmap_depth` ENUM(''chapter'',''section'',''subsection'') NOT NULL DEFAULT ''section'' AFTER `progress_time_bucket`',
    'SELECT ''mindmap_depth already exists'' AS message'
);
PREPARE sn_stmt FROM @sn_sql;
EXECUTE sn_stmt;
DEALLOCATE PREPARE sn_stmt;

SET @sn_has_chapter_chart_style = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @sn_books_table
      AND COLUMN_NAME = 'chapter_chart_style'
);
SET @sn_sql = IF(
    @sn_has_chapter_chart_style = 0,
    'ALTER TABLE `books` ADD COLUMN `chapter_chart_style` ENUM(''rounded'',''horizontal'') NOT NULL DEFAULT ''rounded'' AFTER `mindmap_depth`',
    'SELECT ''chapter_chart_style already exists'' AS message'
);
PREPARE sn_stmt FROM @sn_sql;
EXECUTE sn_stmt;
DEALLOCATE PREPARE sn_stmt;
