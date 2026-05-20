-- ─────────────────────────────────────────────────────────────────
-- FLEXIcontent 6.1.0-beta.11 — Seed 6 expressive themes
--
-- Pro Templates v3 (beta.9) shipped CSS + PresetLibrary entries for
-- 6 expressive themes (aurora / sunset / ocean-glass / cyber-neon /
-- pastel-dream / monochrome-plus), but did not seed the
-- #__flexicontent_pro_themes DB table. Result: admin Themes Layout
-- Manager only showed the single hard-coded default theme; designers
-- could not pick the new themes without hand-editing layout JSON.
--
-- Idempotent: each row uses INSERT ... SELECT ... FROM dual WHERE
-- NOT EXISTS so re-running does not duplicate.
--
-- All theme_data values keep parity with PresetLibrary::themePresets()
-- entries and the CSS [data-fcpt-theme="..."] selectors in
-- site/assets/css/protemplate_frontend.css. Editing one of the three
-- locations without updating the others orphans the theme.
--
-- A11y notes (cleared 2026-05-20 round 3):
--   - focus_ring pinned to accent (cyber-neon = cyan exception)
--   - cyber-neon body font = Inter (sans), not mono
--   - ocean-glass body contrast = 10.3:1 (not 12:1)
-- ─────────────────────────────────────────────────────────────────

INSERT INTO `#__flexicontent_pro_themes`
	(`title`, `theme_data`, `state`, `ordering`, `created`, `created_by`, `modified`, `modified_by`, `checked_out`, `checked_out_time`)
SELECT 'Aurora',
	'{"colors":{"accent":"#7c3aed","accent_grad":"linear-gradient(135deg, #06b6d4 0%, #8b5cf6 50%, #ec4899 100%)","surface":"#ffffff","surface_alt":"#fafbff","text":"#0f172a","text_muted":"#475569","border":"#e0e7ff","focus_ring":"#7c3aed"},"typography":{"family":"Inter, system-ui, sans-serif","family_heading":"Inter, system-ui, sans-serif","scale":1.0,"line_height":1.6},"radius":"md","mode":"light","preset_key":"aurora"}',
	1, 10, NOW(), 0, NULL, 0, NULL, NULL
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `#__flexicontent_pro_themes` WHERE `title` = 'Aurora');

INSERT INTO `#__flexicontent_pro_themes`
	(`title`, `theme_data`, `state`, `ordering`, `created`, `created_by`, `modified`, `modified_by`, `checked_out`, `checked_out_time`)
SELECT 'Sunset',
	'{"colors":{"accent":"#c2410c","accent_grad":"linear-gradient(135deg, #fb923c 0%, #f43f5e 60%, #a855f7 100%)","surface":"#fffaf5","surface_alt":"#fff5eb","text":"#1c1917","text_muted":"#57534e","border":"#fde4cd","focus_ring":"#c2410c"},"typography":{"family":"Outfit, system-ui, sans-serif","family_heading":"Outfit, system-ui, sans-serif","scale":1.0,"line_height":1.6},"radius":"md","mode":"light","preset_key":"sunset"}',
	1, 11, NOW(), 0, NULL, 0, NULL, NULL
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `#__flexicontent_pro_themes` WHERE `title` = 'Sunset');

INSERT INTO `#__flexicontent_pro_themes`
	(`title`, `theme_data`, `state`, `ordering`, `created`, `created_by`, `modified`, `modified_by`, `checked_out`, `checked_out_time`)
SELECT 'Ocean Glass',
	'{"colors":{"accent":"#0e7490","accent_grad":"linear-gradient(135deg, #06b6d4 0%, #0e7490 50%, #1e40af 100%)","surface":"#ffffff","surface_alt":"#f0fdfa","text":"#134e4a","text_muted":"#3f6359","border":"#99f6e4","focus_ring":"#0e7490"},"typography":{"family":"\\"DM Sans\\", system-ui, sans-serif","family_heading":"\\"DM Sans\\", system-ui, sans-serif","scale":1.0,"line_height":1.6},"radius":"lg","mode":"light","preset_key":"ocean-glass"}',
	1, 12, NOW(), 0, NULL, 0, NULL, NULL
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `#__flexicontent_pro_themes` WHERE `title` = 'Ocean Glass');

INSERT INTO `#__flexicontent_pro_themes`
	(`title`, `theme_data`, `state`, `ordering`, `created`, `created_by`, `modified`, `modified_by`, `checked_out`, `checked_out_time`)
SELECT 'Cyber Neon',
	'{"colors":{"accent":"#ec4899","accent_grad":"linear-gradient(135deg, #ec4899 0%, #06b6d4 100%)","surface":"#0a0a0f","surface_alt":"#14141f","text":"#fafafa","text_muted":"#a3a3a3","border":"#404040","focus_ring":"#06b6d4"},"typography":{"family":"Inter, system-ui, sans-serif","family_heading":"\\"JetBrains Mono\\", ui-monospace, SFMono-Regular, monospace","scale":1.0,"line_height":1.55},"radius":"sm","mode":"dark","preset_key":"cyber-neon"}',
	1, 13, NOW(), 0, NULL, 0, NULL, NULL
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `#__flexicontent_pro_themes` WHERE `title` = 'Cyber Neon');

INSERT INTO `#__flexicontent_pro_themes`
	(`title`, `theme_data`, `state`, `ordering`, `created`, `created_by`, `modified`, `modified_by`, `checked_out`, `checked_out_time`)
SELECT 'Pastel Dream',
	'{"colors":{"accent":"#be185d","accent_grad":"linear-gradient(135deg, #f472b6 0%, #c084fc 50%, #60a5fa 100%)","surface":"#ffffff","surface_alt":"#fdf4ff","text":"#500724","text_muted":"#831843","border":"#fbcfe8","focus_ring":"#be185d"},"typography":{"family":"Quicksand, \\"Nunito Sans\\", system-ui, sans-serif","family_heading":"Quicksand, \\"Nunito Sans\\", system-ui, sans-serif","scale":1.05,"line_height":1.7},"radius":"lg","mode":"light","preset_key":"pastel-dream"}',
	1, 14, NOW(), 0, NULL, 0, NULL, NULL
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `#__flexicontent_pro_themes` WHERE `title` = 'Pastel Dream');

INSERT INTO `#__flexicontent_pro_themes`
	(`title`, `theme_data`, `state`, `ordering`, `created`, `created_by`, `modified`, `modified_by`, `checked_out`, `checked_out_time`)
SELECT 'Monochrome Plus',
	'{"colors":{"accent":"#000000","accent_grad":"linear-gradient(135deg, #000000, #404040)","surface":"#ffffff","surface_alt":"#fafafa","text":"#000000","text_muted":"#525252","border":"#d4d4d4","focus_ring":"#000000"},"typography":{"family":"Inter, system-ui, sans-serif","family_heading":"Inter, system-ui, sans-serif","scale":1.0,"line_height":1.6},"radius":"md","mode":"light","preset_key":"monochrome-plus"}',
	1, 15, NOW(), 0, NULL, 0, NULL, NULL
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `#__flexicontent_pro_themes` WHERE `title` = 'Monochrome Plus');
