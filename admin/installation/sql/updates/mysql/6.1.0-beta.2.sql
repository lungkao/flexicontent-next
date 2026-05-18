-- =============================================================================
-- FLEXIcontent Pro Templates — Schema update for 6.1.0-beta.2
--
-- Adds view_scope column so a Pro Layout can declare whether it renders the
-- single-item view ('item') or the multi-item category view ('category').
--
-- Idempotent + MySQL/MariaDB compatible. Uses INFORMATION_SCHEMA + PREPARE
-- instead of ADD COLUMN IF NOT EXISTS (which is MariaDB-only — MySQL 8.x
-- does NOT support it as of 8.0.x).
-- =============================================================================

SET @col_exists := (
	SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = '#__flexicontent_pro_layouts'
		AND COLUMN_NAME = 'view_scope'
);
SET @sql_add_col := IF(@col_exists = 0,
	'ALTER TABLE `#__flexicontent_pro_layouts` ADD COLUMN `view_scope` VARCHAR(16) NOT NULL DEFAULT ''item'' AFTER `assignment_value`',
	'SELECT 1');
PREPARE stmt FROM @sql_add_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `#__flexicontent_pro_layouts`
	SET `view_scope` = 'item'
	WHERE `view_scope` IS NULL OR `view_scope` = '';

SET @idx_exists := (
	SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = '#__flexicontent_pro_layouts'
		AND INDEX_NAME = 'idx_state_viewscope'
);
SET @sql_add_idx := IF(@idx_exists = 0,
	'ALTER TABLE `#__flexicontent_pro_layouts` ADD KEY `idx_state_viewscope` (`state`, `view_scope`)',
	'SELECT 1');
PREPARE stmt FROM @sql_add_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
