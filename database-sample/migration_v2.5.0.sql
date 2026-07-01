-- ShioriNote v2.5.0 migration
-- Adds per-book mindmap root display setting.

SET @db_name := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE books ADD COLUMN mindmap_root ENUM(''chapter'',''book'') NOT NULL DEFAULT ''chapter'' AFTER mindmap_depth',
        'SELECT ''books.mindmap_root already exists'' AS message'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'books'
      AND COLUMN_NAME = 'mindmap_root'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
