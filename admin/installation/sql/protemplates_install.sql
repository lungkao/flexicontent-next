-- =============================================================================
-- FLEXIcontent Pro Templates — Install SQL
-- Version : 6.1.0-alpha.5
-- Tables  : flexicontent_pro_layouts, _pro_themes, _pro_revisions
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1. Pro Layouts  (drag-and-drop layout builder — JSON storage)
--    * ผูกได้กับ type_id (FLEXIContent Type) และ/หรือ catid (category)
--    * assignment_type กำหนดลำดับ priority ตอน resolve layout
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__flexicontent_pro_layouts` (
  `id`               INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(255)        NOT NULL DEFAULT '',
  -- ผูกกับ FLEXIContent Type (0 = ทุก type)
  `type_id`          INT(11)             NOT NULL DEFAULT 0,
  -- ผูกกับ Category (0 = ทุก category)
  `catid`            INT(11)             NOT NULL DEFAULT 0,
  -- วิธีการ assign: global | type | category | item | menu
  `assignment_type`  VARCHAR(32)         NOT NULL DEFAULT 'global',
  -- ค่าสำหรับ assignment_type=item หรือ =menu (เก็บ ID เป็น string)
  `assignment_value` VARCHAR(100)        NOT NULL DEFAULT '',
  -- view scope: 'item' = single article view, 'category' = category list view
  `view_scope`       VARCHAR(16)         NOT NULL DEFAULT 'item',
  -- JSON ของ layout builder {"version":2,"sections":[...]}
  `layout_data`      LONGTEXT            NULL,
  -- ID ของ theme ที่ใช้ (0 = ไม่ใช้ theme)
  `theme_id`         INT(11)             NOT NULL DEFAULT 0,
  `state`            TINYINT(1)          NOT NULL DEFAULT 1,
  `ordering`         INT(11)             NOT NULL DEFAULT 0,
  `note`             VARCHAR(255)        NOT NULL DEFAULT '',
  `created`          DATETIME            NULL DEFAULT NULL,
  `created_by`       INT(11)             NOT NULL DEFAULT 0,
  `modified`         DATETIME            NULL DEFAULT NULL,
  `modified_by`      INT(11)             NOT NULL DEFAULT 0,
  `checked_out`      INT(11)             NULL DEFAULT NULL,
  `checked_out_time` DATETIME            NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_state`        (`state`),
  KEY `idx_type_cat`     (`type_id`, `catid`),
  KEY `idx_assignment`   (`assignment_type`, `assignment_value`(50)),
  KEY `idx_state_viewscope` (`state`, `view_scope`),
  KEY `idx_checkout`     (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- 2. Pro Themes  (color scheme + typography — JSON เหมือน fieldlayout)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__flexicontent_pro_themes` (
  `id`               INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(255)        NOT NULL DEFAULT '',
  -- JSON: {"colors":{"accent":"#2563eb",...},"fontFamily":"Inter","fontSize":"16"}
  `theme_data`       LONGTEXT            NULL,
  `state`            TINYINT(1)          NOT NULL DEFAULT 1,
  `ordering`         INT(11)             NOT NULL DEFAULT 0,
  `created`          DATETIME            NULL DEFAULT NULL,
  `created_by`       INT(11)             NOT NULL DEFAULT 0,
  `modified`         DATETIME            NULL DEFAULT NULL,
  `modified_by`      INT(11)             NOT NULL DEFAULT 0,
  `checked_out`      INT(11)             NULL DEFAULT NULL,
  `checked_out_time` DATETIME            NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_state`    (`state`),
  KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- 3. Pro Revisions  (revision history สำหรับ layout — ไม่มี limit ตาม config)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__flexicontent_pro_revisions` (
  `id`            INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `layout_id`     INT(11) UNSIGNED    NOT NULL DEFAULT 0,
  `title`         VARCHAR(255)        NOT NULL DEFAULT '',
  `layout_data`   LONGTEXT            NULL,
  -- manual | autosave
  `revision_type` VARCHAR(32)         NOT NULL DEFAULT 'manual',
  `created`       DATETIME            NULL DEFAULT NULL,
  `created_by`    INT(11)             NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_layout_id` (`layout_id`),
  KEY `idx_type`      (`revision_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
