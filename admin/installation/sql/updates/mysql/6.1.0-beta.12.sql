-- ─────────────────────────────────────────────────────────────────
-- FLEXIcontent 6.1.0-beta.12 — Pro Template / Pro Theme revisions
--
-- Adds the revision store consumed by the Layout editor autosave
-- loop. Each save (manual or timer-triggered) inserts one row
-- capturing the layout JSON + optional theme JSON snapshot.
--
-- `parent_type` discriminates between template revisions and theme
-- revisions so the same table backs both editors. The composite
-- index (parent_type, parent_id, created) supports the "show recent
-- revisions" panel without a table scan.
--
-- Pruning policy: helpers/protemplate/Revisions::prune() keeps the
-- most-recent N rows per (parent_type, parent_id) tuple (default
-- 20). Pruning runs on each insert.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS so re-running is safe.
-- ─────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `#__flexicontent_pro_layout_revisions` (
	`id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`parent_id`   INT UNSIGNED NOT NULL,
	`parent_type` ENUM('template','theme') NOT NULL,
	`layout_json` LONGTEXT NOT NULL,
	`theme_json`  LONGTEXT NULL,
	`note`        VARCHAR(255) NULL,
	`created`     DATETIME NOT NULL,
	`created_by`  INT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_parent_created` (`parent_type`, `parent_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
