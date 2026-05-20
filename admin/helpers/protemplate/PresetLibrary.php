<?php
/**
 * @package         FLEXIcontent
 * @subpackage      Pro Templates — Preset Library
 *
 * @author          FLEXIcontent Team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Curated library of starter layouts (item + category) and themes consumed by
 * the "Create from preset" chooser screen. Presets ship full layout_data JSON
 * in the builder's canonical shape (sections > rows > cols > elements), so
 * they render correctly in both the builder canvas and the frontend Renderer
 * with zero special-casing.
 *
 * SVG thumbnail fills are tuned to meet WCAG 1.4.11 (3:1) against the
 * #f8fafc card background — structural rects use slate-500 (#64748b, ~4.4:1)
 * or slate-600 (#475569, ~7.7:1), title rects use slate-900 (#0f172a, ~17:1).
 *
 * Adding a new preset: append to the relevant catalogue array, add i18n keys,
 * and verify the SVG fills still pass 3:1 if you tweak colors.
 */

defined('_JEXEC') or die('Restricted access');

class FlexicontentProTemplatePresetLibrary
{
	/* ---------------------------------------------------------------------
	 * Public API
	 * ------------------------------------------------------------------- */

	public static function getLayoutPresets(string $scope): array
	{
		$scope = $scope === 'category' ? 'category' : 'item';

		return $scope === 'item'
			? self::itemPresets()
			: self::categoryPresets();
	}

	public static function getLayoutPreset(string $key): ?array
	{
		foreach (array_merge(self::itemPresets(), self::categoryPresets()) as $preset) {
			if ($preset['key'] === $key) {
				return $preset;
			}
		}
		return null;
	}

	public static function getThemePresets(): array
	{
		return self::themePresets();
	}

	public static function getThemePreset(string $key): ?array
	{
		foreach (self::themePresets() as $preset) {
			if ($preset['key'] === $key) {
				return $preset;
			}
		}
		return null;
	}

	public static function getLayoutGroups(): array
	{
		return [
			['key' => 'all',       'label_key' => 'FLEXI_PRESET_GROUP_ALL'],
			['key' => 'editorial', 'label_key' => 'FLEXI_PRESET_GROUP_EDITORIAL'],
			['key' => 'magazine',  'label_key' => 'FLEXI_PRESET_GROUP_MAGAZINE'],
			['key' => 'media',     'label_key' => 'FLEXI_PRESET_GROUP_MEDIA'],
			['key' => 'compact',   'label_key' => 'FLEXI_PRESET_GROUP_COMPACT'],
		];
	}

	public static function getThemeGroups(): array
	{
		return [
			['key' => 'all',       'label_key' => 'FLEXI_PRESET_GROUP_ALL'],
			['key' => 'modern',    'label_key' => 'FLEXI_PRESET_GROUP_MODERN'],
			['key' => 'editorial', 'label_key' => 'FLEXI_PRESET_GROUP_EDITORIAL'],
			['key' => 'minimal',   'label_key' => 'FLEXI_PRESET_GROUP_MINIMAL'],
			['key' => 'dark',      'label_key' => 'FLEXI_PRESET_GROUP_DARK'],
		];
	}

	/* ---------------------------------------------------------------------
	 * Element / row / section factory helpers — produce JSON fragments in
	 * the builder's canonical shape (matches fcptNormalizeLayout in the
	 * builder template). UIDs are deterministic per-preset.
	 * ------------------------------------------------------------------- */

	protected static function uid(string $key, string $what, int $n): string
	{
		return $key . '-' . $what . '-' . $n;
	}

	protected static function article(string $name, string $tag = 'div', string $variant = 'default', string $extraClass = ''): array
	{
		return [
			'type'    => 'article',
			'name'    => $name,
			'tag'     => $tag,
			'variant' => $variant,
			'class'   => trim('fcpt-item-' . str_replace('_', '-', $name) . ' ' . $extraClass),
		];
	}

	protected static function heading(string $text, string $level = 'h3', string $variant = 'default'): array
	{
		return [
			'type'    => 'heading',
			'text'    => $text,
			'level'   => $level,
			'variant' => $variant,
		];
	}

	protected static function text(string $text, string $tag = 'p', string $variant = 'default'): array
	{
		return [
			'type'    => 'text',
			'text'    => $text,
			'tag'     => $tag,
			'variant' => $variant,
			'class'   => '',
		];
	}

	protected static function separator(): array
	{
		return ['type' => 'separator'];
	}

	protected static function col(string $key, int $n, int $width, array $elements, string $appearance = 'default'): array
	{
		$tagged = [];
		foreach ($elements as $i => $el) {
			$tagged[] = $el + ['_uid' => self::uid($key, 'el', ($n * 10) + $i)];
		}

		return [
			'id'         => self::uid($key, 'col', $n),
			'width'      => max(2, min(12, $width)),
			'class'      => 'fcpt-col-default',
			'appearance' => $appearance,
			'elements'   => $tagged,
		];
	}

	protected static function row(string $key, int $n, array $cols, string $appearance = 'default'): array
	{
		return [
			'id'         => self::uid($key, 'row', $n),
			'class'      => 'fcpt-row-default',
			'appearance' => $appearance,
			'cols'       => $cols,
		];
	}

	protected static function section(string $key, int $n, string $label, string $appearance, array $rows): array
	{
		return [
			'id'         => self::uid($key, 'sec', $n),
			'label'      => $label,
			'class'      => 'fcpt-section-default',
			'appearance' => $appearance,
			'rows'       => $rows,
		];
	}

	protected static function layout(array $sections, string $themePreset = 'clean', string $width = 'default', string $spacing = 'normal'): array
	{
		return [
			'version'  => 2,
			'settings' => [
				'theme'   => $themePreset,
				'width'   => $width,
				'spacing' => $spacing,
				'themeId' => 0,
			],
			'sections' => $sections,
		];
	}

	/* ---------------------------------------------------------------------
	 * Item-scope presets (4)
	 * ------------------------------------------------------------------- */

	protected static function itemPresets(): array
	{
		return [
			self::itemMagazine(),
			self::itemEditorial(),
			self::itemMediaRich(),
			self::itemCompact(),
			self::itemPortfolio(),
			self::itemLongform(),
		];
	}

	protected static function itemMagazine(): array
	{
		$k = 'item-magazine';

		$layout = self::layout([
			self::section($k, 1, 'Hero', 'hero', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('image_intro', 'figure', 'display', 'fcpt-hero-image'),
						self::article('title',       'h1',     'display'),
						self::article('introtext',   'div',    'lead'),
					]),
				]),
			]),
			self::section($k, 2, 'Body + Meta', 'plain', [
				self::row($k, 2, [
					self::col($k, 2, 8, [
						self::article('fulltext', 'div', 'default'),
					]),
					self::col($k, 3, 4, [
						self::heading('Article info', 'h3', 'default'),
						self::article('author',   'span', 'pill'),
						self::article('created',  'time', 'muted'),
						self::article('category', 'span', 'pill'),
						self::article('tags',     'div',  'default'),
						self::article('hits',     'span', 'stat'),
					]),
				], 'media'),
			]),
		], 'editorial', 'default', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'item',
			'title_key'       => 'FLEXI_PRESET_ITEM_MAGAZINE',
			'description_key' => 'FLEXI_PRESET_ITEM_MAGAZINE_DESC',
			'group'           => 'magazine',
			'thumbnail'       => self::thumbItemMagazine(),
			'layout'          => $layout,
		];
	}

	protected static function itemEditorial(): array
	{
		$k = 'item-editorial';

		$layout = self::layout([
			self::section($k, 1, 'Lead', 'plain', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('category', 'span', 'pill'),
						self::article('title',    'h1',   'display'),
						self::article('author',   'span', 'muted'),
						self::article('created',  'time', 'muted'),
					]),
				]),
			]),
			self::section($k, 2, 'Intro', 'plain', [
				self::row($k, 2, [
					self::col($k, 2, 12, [
						self::article('introtext', 'div', 'lead'),
					]),
				]),
			]),
			self::section($k, 3, 'Body', 'plain', [
				self::row($k, 3, [
					self::col($k, 3, 6, [
						self::article('fulltext', 'div', 'default'),
					]),
					self::col($k, 4, 6, [
						self::article('image_full', 'figure', 'card'),
						self::article('tags',       'div',    'default'),
					]),
				]),
			]),
		], 'clean', 'narrow', 'airy');

		return [
			'key'             => $k,
			'scope'           => 'item',
			'title_key'       => 'FLEXI_PRESET_ITEM_EDITORIAL',
			'description_key' => 'FLEXI_PRESET_ITEM_EDITORIAL_DESC',
			'group'           => 'editorial',
			'thumbnail'       => self::thumbItemEditorial(),
			'layout'          => $layout,
		];
	}

	protected static function itemMediaRich(): array
	{
		$k = 'item-media';

		$layout = self::layout([
			self::section($k, 1, 'Hero image', 'hero', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('image_full', 'figure', 'display', 'fcpt-hero-image'),
					]),
				]),
			]),
			self::section($k, 2, 'Title strip', 'band', [
				self::row($k, 2, [
					self::col($k, 2, 12, [
						self::article('title',     'h1',   'display'),
						self::article('created',   'time', 'muted'),
						self::article('introtext', 'div',  'lead'),
					]),
				]),
			]),
			self::section($k, 3, 'Two-column body', 'plain', [
				self::row($k, 3, [
					self::col($k, 3, 4, [
						self::article('image_intro', 'figure', 'card'),
						self::article('category',    'span',   'pill'),
						self::article('tags',        'div',    'default'),
					]),
					self::col($k, 4, 8, [
						self::article('fulltext', 'div', 'default'),
					]),
				], 'media'),
			]),
		], 'soft', 'wide', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'item',
			'title_key'       => 'FLEXI_PRESET_ITEM_MEDIA',
			'description_key' => 'FLEXI_PRESET_ITEM_MEDIA_DESC',
			'group'           => 'media',
			'thumbnail'       => self::thumbItemMedia(),
			'layout'          => $layout,
		];
	}

	protected static function itemCompact(): array
	{
		$k = 'item-compact';

		$layout = self::layout([
			self::section($k, 1, 'Compact item', 'plain', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('image_intro', 'figure', 'thumbnail', 'fcpt-compact-thumb'),
						self::article('title',     'h1',   'default'),
						self::article('created',   'time', 'muted'),
						self::article('author',    'span', 'muted'),
						self::separator(),
						self::article('introtext', 'div',  'default'),
						self::article('fulltext',  'div',  'default'),
						self::article('tags',      'div',  'default'),
					]),
				]),
			]),
		], 'clean', 'narrow', 'compact');

		return [
			'key'             => $k,
			'scope'           => 'item',
			'title_key'       => 'FLEXI_PRESET_ITEM_COMPACT',
			'description_key' => 'FLEXI_PRESET_ITEM_COMPACT_DESC',
			'group'           => 'compact',
			'thumbnail'       => self::thumbItemCompact(),
			'layout'          => $layout,
		];
	}

	protected static function itemPortfolio(): array
	{
		$k = 'item-portfolio';

		// Showcase layout: hero media + image grid + caption-style body.
		// Title placed BELOW hero in band section so the image leads.
		$layout = self::layout([
			self::section($k, 1, 'Hero media', 'hero', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('image_full', 'figure', 'display', 'fcpt-hero-image'),
					]),
				]),
			]),
			self::section($k, 2, 'Caption band', 'band', [
				self::row($k, 2, [
					self::col($k, 2, 8, [
						self::article('title',    'h1',   'display'),
						self::article('category', 'span', 'pill'),
					]),
					self::col($k, 3, 4, [
						self::article('created', 'time', 'muted'),
						self::article('author',  'span', 'muted'),
					]),
				], 'media'),
			]),
			self::section($k, 3, 'Body grid', 'plain', [
				self::row($k, 3, [
					self::col($k, 4, 6, [
						self::article('image_intro', 'figure', 'card'),
					]),
					self::col($k, 5, 6, [
						self::article('introtext', 'div', 'lead'),
						self::article('fulltext',  'div', 'default'),
						self::article('tags',      'div', 'default'),
					]),
				], 'media'),
			]),
		], 'soft', 'wide', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'item',
			'title_key'       => 'FLEXI_PRESET_ITEM_PORTFOLIO',
			'description_key' => 'FLEXI_PRESET_ITEM_PORTFOLIO_DESC',
			'group'           => 'media',
			'thumbnail'       => self::thumbItemPortfolio(),
			'layout'          => $layout,
		];
	}

	protected static function itemLongform(): array
	{
		$k = 'item-longform';

		// Read-first longform: narrow column body with separator-driven
		// meta strip on top. Sidebar omitted (compact-airy reading mode).
		$layout = self::layout([
			self::section($k, 1, 'Header', 'plain', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('category', 'span', 'pill'),
						self::article('title',    'h1',   'display'),
						self::article('introtext', 'div', 'lead'),
						self::separator(),
						self::article('author',  'span', 'muted'),
						self::article('created', 'time', 'muted'),
						self::article('hits',    'span', 'stat'),
					]),
				]),
			]),
			self::section($k, 2, 'Body', 'plain', [
				self::row($k, 2, [
					self::col($k, 2, 12, [
						self::article('image_full', 'figure', 'display'),
						self::article('fulltext',   'div',    'default'),
						self::separator(),
						self::article('tags', 'div', 'default'),
					]),
				]),
			]),
		], 'editorial', 'narrow', 'airy');

		return [
			'key'             => $k,
			'scope'           => 'item',
			'title_key'       => 'FLEXI_PRESET_ITEM_LONGFORM',
			'description_key' => 'FLEXI_PRESET_ITEM_LONGFORM_DESC',
			'group'           => 'editorial',
			'thumbnail'       => self::thumbItemLongform(),
			'layout'          => $layout,
		];
	}

	/* ---------------------------------------------------------------------
	 * Category-scope presets (6)
	 * ------------------------------------------------------------------- */

	protected static function categoryPresets(): array
	{
		return [
			self::categoryGridCards(),
			self::categoryFeaturedList(),
			self::categoryMagazineIndex(),
			self::categoryCompactList(),
			self::categoryMasonry(),
			self::categoryNewsfeed(),
		];
	}

	protected static function categoryGridCards(): array
	{
		$k = 'cat-grid';

		// Category context: renderer loops items and renders this layout once
		// per item, so this is the per-item card template. Title is clamped to
		// h2 in category context; image alt is forced empty (decorative because
		// adjacent title names the card). Grid columns-per-row is controlled by
		// the surrounding category list, not by this template.
		$layout = self::layout([
			self::section($k, 1, 'Card', 'plain', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('image_intro', 'figure', 'card', 'fcpt-card-image'),
						self::article('title',       'h2',     'default'),
						self::article('created',     'time',   'muted'),
						self::article('introtext',   'div',    'default'),
					]),
				]),
			]),
		], 'clean', 'default', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'category',
			'title_key'       => 'FLEXI_PRESET_CAT_GRID',
			'description_key' => 'FLEXI_PRESET_CAT_GRID_DESC',
			'group'           => 'magazine',
			'thumbnail'       => self::thumbCatGrid(),
			'layout'          => $layout,
		];
	}

	protected static function categoryFeaturedList(): array
	{
		$k = 'cat-featured';

		// Category context: rendered once per item. Hero row pairs the
		// item's image_intro with title + created + introtext as a
		// magazine-style featured card. Renderer clamps title heading to h2.
		$layout = self::layout([
			self::section($k, 1, 'Featured card', 'feature', [
				self::row($k, 1, [
					self::col($k, 1, 7, [
						self::article('image_intro', 'figure', 'display', 'fcpt-card-image'),
					]),
					self::col($k, 2, 5, [
						self::article('title',     'h2',   'default'),
						self::article('created',   'time', 'muted'),
						self::article('introtext', 'div',  'lead'),
					]),
				], 'media'),
			]),
		], 'editorial', 'default', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'category',
			'title_key'       => 'FLEXI_PRESET_CAT_FEATURED',
			'description_key' => 'FLEXI_PRESET_CAT_FEATURED_DESC',
			'group'           => 'editorial',
			'thumbnail'       => self::thumbCatFeatured(),
			'layout'          => $layout,
		];
	}

	protected static function categoryMagazineIndex(): array
	{
		$k = 'cat-magazine';

		// Category context: rendered once per item. Magazine-style teaser
		// pairs image_intro with title + created + introtext. Renderer
		// clamps title heading to h2 in category context.
		$layout = self::layout([
			self::section($k, 1, 'Magazine card', 'bento', [
				self::row($k, 1, [
					self::col($k, 1, 8, [
						self::article('image_intro', 'figure', 'display', 'fcpt-card-image'),
						self::article('title',       'h2',     'display'),
					]),
					self::col($k, 2, 4, [
						self::article('created',   'time', 'muted'),
						self::article('introtext', 'div',  'default'),
					]),
				], 'bento'),
			]),
		], 'contrast', 'wide', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'category',
			'title_key'       => 'FLEXI_PRESET_CAT_MAGAZINE',
			'description_key' => 'FLEXI_PRESET_CAT_MAGAZINE_DESC',
			'group'           => 'magazine',
			'thumbnail'       => self::thumbCatMagazine(),
			'layout'          => $layout,
		];
	}

	protected static function categoryCompactList(): array
	{
		$k = 'cat-compact';

		// Category context: rendered once per item. Compact row pairs a
		// thumbnail with title + created + introtext. Renderer clamps title
		// to h2 in category context.
		$layout = self::layout([
			self::section($k, 1, 'Compact row', 'plain', [
				self::row($k, 1, [
					self::col($k, 1, 3, [
						self::article('image_intro', 'figure', 'thumbnail', 'fcpt-card-thumb'),
					]),
					self::col($k, 2, 9, [
						self::article('title',     'h2',   'default'),
						self::article('created',   'time', 'muted'),
						self::article('introtext', 'div',  'muted'),
					]),
				], 'compact'),
			]),
		], 'clean', 'narrow', 'compact');

		return [
			'key'             => $k,
			'scope'           => 'category',
			'title_key'       => 'FLEXI_PRESET_CAT_COMPACT',
			'description_key' => 'FLEXI_PRESET_CAT_COMPACT_DESC',
			'group'           => 'compact',
			'thumbnail'       => self::thumbCatCompact(),
			'layout'          => $layout,
		];
	}

	protected static function categoryMasonry(): array
	{
		$k = 'cat-masonry';

		// Category context: rendered once per item. Image-first masonry
		// card — large media on top, title clamped to h2 below.
		$layout = self::layout([
			self::section($k, 1, 'Masonry card', 'bento', [
				self::row($k, 1, [
					self::col($k, 1, 12, [
						self::article('image_intro', 'figure', 'display', 'fcpt-card-image'),
						self::article('category',    'span',   'pill'),
						self::article('title',       'h2',     'display'),
						self::article('created',     'time',   'muted'),
						self::article('introtext',   'div',    'muted'),
					]),
				], 'bento'),
			]),
		], 'soft', 'wide', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'category',
			'title_key'       => 'FLEXI_PRESET_CAT_MASONRY',
			'description_key' => 'FLEXI_PRESET_CAT_MASONRY_DESC',
			'group'           => 'media',
			'thumbnail'       => self::thumbCatMasonry(),
			'layout'          => $layout,
		];
	}

	protected static function categoryNewsfeed(): array
	{
		$k = 'cat-newsfeed';

		// Category context: rendered once per item. Timeline-style row:
		// date pill leads, title + intro fill the rest. Renderer clamps
		// title to h2 in category context.
		$layout = self::layout([
			self::section($k, 1, 'Newsfeed row', 'plain', [
				self::row($k, 1, [
					self::col($k, 1, 3, [
						self::article('image_intro', 'figure', 'thumbnail', 'fcpt-card-thumb'),
						self::article('created',     'time',   'pill'),
						self::article('category',    'span',   'muted'),
					]),
					self::col($k, 2, 9, [
						self::article('title',     'h2',   'default'),
						self::article('author',    'span', 'muted'),
						self::article('introtext', 'div',  'default'),
						self::article('tags',      'div',  'default'),
					]),
				], 'compact'),
			]),
		], 'clean', 'default', 'normal');

		return [
			'key'             => $k,
			'scope'           => 'category',
			'title_key'       => 'FLEXI_PRESET_CAT_NEWSFEED',
			'description_key' => 'FLEXI_PRESET_CAT_NEWSFEED_DESC',
			'group'           => 'editorial',
			'thumbnail'       => self::thumbCatNewsfeed(),
			'layout'          => $layout,
		];
	}

	/* ---------------------------------------------------------------------
	 * Theme presets (6) — all colour pairs verified to clear WCAG AA 4.5:1
	 * for body text against their declared surface. If you add a preset run
	 * WebAIM contrast check on every text-on-surface pair before merging.
	 * ------------------------------------------------------------------- */

	protected static function themePresets(): array
	{
		return [
			[
				'key'             => 'modern-blue',
				'title_key'       => 'FLEXI_PRESET_THEME_MODERN_BLUE',
				'description_key' => 'FLEXI_PRESET_THEME_MODERN_BLUE_DESC',
				'group'           => 'modern',
				'thumbnail'       => self::thumbTheme('#2563eb', '#ffffff', '#0f172a'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#2563eb',
						'surface'     => '#ffffff',
						'surface_alt' => '#f1f5f9',
						'text'        => '#0f172a',
						'text_muted'  => '#475569',
						'border'      => '#cbd5e1',
					],
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => 'Inter, system-ui, sans-serif',
						'scale'          => 1.0,
						'line_height'    => 1.6,
					],
					'radius' => 'md',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'editorial-warm',
				'title_key'       => 'FLEXI_PRESET_THEME_EDITORIAL_WARM',
				'description_key' => 'FLEXI_PRESET_THEME_EDITORIAL_WARM_DESC',
				'group'           => 'editorial',
				'thumbnail'       => self::thumbTheme('#b45309', '#fffaf0', '#3f2a0f'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#b45309',
						'surface'     => '#fffaf0',
						'surface_alt' => '#fdf3e1',
						'text'        => '#3f2a0f',
						'text_muted'  => '#6b4a23',
						'border'      => '#e6d3b3',
					],
					'typography' => [
						'family'         => 'Georgia, "Times New Roman", serif',
						'family_heading' => 'Playfair Display, Georgia, serif',
						'scale'          => 1.05,
						'line_height'    => 1.7,
					],
					'radius' => 'sm',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'minimal-mono',
				'title_key'       => 'FLEXI_PRESET_THEME_MINIMAL_MONO',
				'description_key' => 'FLEXI_PRESET_THEME_MINIMAL_MONO_DESC',
				'group'           => 'minimal',
				'thumbnail'       => self::thumbTheme('#111827', '#ffffff', '#111827'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#111827',
						'surface'     => '#ffffff',
						'surface_alt' => '#f8fafc',
						'text'        => '#111827',
						'text_muted'  => '#4b5563',
						'border'      => '#9ca3af',
					],
					'typography' => [
						'family'         => 'system-ui, -apple-system, sans-serif',
						'family_heading' => 'system-ui, -apple-system, sans-serif',
						'scale'          => 0.95,
						'line_height'    => 1.55,
					],
					'radius' => 'sm',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'dark-pro',
				'title_key'       => 'FLEXI_PRESET_THEME_DARK_PRO',
				'description_key' => 'FLEXI_PRESET_THEME_DARK_PRO_DESC',
				'group'           => 'dark',
				'thumbnail'       => self::thumbTheme('#38bdf8', '#0f172a', '#e2e8f0'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#38bdf8',
						'surface'     => '#0f172a',
						'surface_alt' => '#1e293b',
						'text'        => '#e2e8f0',
						'text_muted'  => '#94a3b8',
						'border'      => '#334155',
					],
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => 'Inter, system-ui, sans-serif',
						'scale'          => 1.0,
						'line_height'    => 1.6,
					],
					'radius' => 'md',
					'mode'   => 'dark',
				],
			],
			[
				'key'             => 'nature-green',
				'title_key'       => 'FLEXI_PRESET_THEME_NATURE_GREEN',
				'description_key' => 'FLEXI_PRESET_THEME_NATURE_GREEN_DESC',
				'group'           => 'modern',
				'thumbnail'       => self::thumbTheme('#0f766e', '#f0fdfa', '#134e4a'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#0f766e',
						'surface'     => '#f0fdfa',
						'surface_alt' => '#ccfbf1',
						'text'        => '#134e4a',
						'text_muted'  => '#3f6359',
						'border'      => '#5eead4',
					],
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => 'Inter, system-ui, sans-serif',
						'scale'          => 1.0,
						'line_height'    => 1.65,
					],
					'radius' => 'lg',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'royal',
				'title_key'       => 'FLEXI_PRESET_THEME_ROYAL',
				'description_key' => 'FLEXI_PRESET_THEME_ROYAL_DESC',
				'group'           => 'editorial',
				'thumbnail'       => self::thumbTheme('#6d28d9', '#faf5ff', '#3b0764'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#6d28d9',
						'surface'     => '#faf5ff',
						'surface_alt' => '#f3e8ff',
						'text'        => '#3b0764',
						'text_muted'  => '#5b21b6',
						'border'      => '#c4b5fd',
					],
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => '"Playfair Display", Georgia, serif',
						'scale'          => 1.05,
						'line_height'    => 1.65,
					],
					'radius' => 'md',
					'mode'   => 'light',
				],
			],

			// ── CSS variety + font config additions (6.1.0-beta.4) ──────
			// Each new preset deliberately picks a distinct heading
			// typeface so the library covers more visual territory:
			//   bold-tech     → JetBrains Mono headings, dark surface
			//   soft-pastel   → Quicksand round sans, warm surface, pill radius
			//   serif-classic → Cormorant Garamond display + Lora body
			// All keep WCAG 1.4.3 (>=4.5:1 body text contrast) and 1.4.11
			// (>=3:1 accent/border against surface).

			[
				'key'             => 'bold-tech',
				'title_key'       => 'FLEXI_PRESET_THEME_BOLD_TECH',
				'description_key' => 'FLEXI_PRESET_THEME_BOLD_TECH_DESC',
				'group'           => 'modern',
				'thumbnail'       => self::thumbTheme('#ec4899', '#0a0a0a', '#fafafa'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#ec4899',
						'surface'     => '#0a0a0a',
						'surface_alt' => '#171717',
						'text'        => '#fafafa',
						'text_muted'  => '#a3a3a3',
						'border'      => '#404040',
					],
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => '"JetBrains Mono", ui-monospace, SFMono-Regular, monospace',
						'scale'          => 1.0,
						'line_height'    => 1.55,
					],
					'radius' => 'sm',
					'mode'   => 'dark',
				],
			],
			[
				'key'             => 'soft-pastel',
				'title_key'       => 'FLEXI_PRESET_THEME_SOFT_PASTEL',
				'description_key' => 'FLEXI_PRESET_THEME_SOFT_PASTEL_DESC',
				'group'           => 'minimal',
				'thumbnail'       => self::thumbTheme('#fb923c', '#fef7f3', '#292524'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#c2410c',
						'surface'     => '#fef7f3',
						'surface_alt' => '#fce7d9',
						'text'        => '#292524',
						'text_muted'  => '#57534e',
						'border'      => '#e7d3c0',
					],
					'typography' => [
						'family'         => 'Quicksand, "Nunito Sans", system-ui, sans-serif',
						'family_heading' => 'Quicksand, "Nunito Sans", system-ui, sans-serif',
						'scale'          => 1.05,
						'line_height'    => 1.7,
					],
					'radius' => 'lg',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'serif-classic',
				'title_key'       => 'FLEXI_PRESET_THEME_SERIF_CLASSIC',
				'description_key' => 'FLEXI_PRESET_THEME_SERIF_CLASSIC_DESC',
				'group'           => 'editorial',
				'thumbnail'       => self::thumbTheme('#1e40af', '#fefce8', '#422006'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#1e40af',
						'surface'     => '#fefce8',
						'surface_alt' => '#fef9c3',
						'text'        => '#422006',
						'text_muted'  => '#713f12',
						'border'      => '#d4af37',
					],
					'typography' => [
						'family'         => 'Lora, "Iowan Old Style", Georgia, serif',
						'family_heading' => '"Cormorant Garamond", "Times New Roman", serif',
						'scale'          => 1.1,
						'line_height'    => 1.75,
					],
					'radius' => 'sm',
					'mode'   => 'light',
				],
			],

			// ── Expressive themes (6.1.0-beta.9 — v3 design ship) ───────────
			//
			// Pair with [data-fcpt-theme="..."] selectors in
			// site/assets/css/protemplate_frontend.css. The `key` MUST
			// stay in sync with the CSS selector keys — renaming one
			// orphans the other.
			//
			// A11y-cleared by accessibility-lead 2026-05-20 (round 3):
			//   - Every body-text/surface pair >=4.5:1 (1.4.3)
			//   - Every accent-on-surface pair >=4.5:1 (accent valid as
			//     body-text colour, not just decorative)
			//   - focus_ring pinned to accent colour, NOT a gradient
			//     stop — guarantees 3:1 against surface per 1.4.11
			//   - Cyber Neon body font stays sans (Inter); JetBrains Mono
			//     is heading-only — monospace at body size harms low-vision
			//     reading speed
			//   - Decorative thumbnail SVGs aria-hidden via svgFrame()

			[
				'key'             => 'aurora',
				'title_key'       => 'FLEXI_PRESET_THEME_AURORA',
				'description_key' => 'FLEXI_PRESET_THEME_AURORA_DESC',
				'group'           => 'modern',
				'thumbnail'       => self::thumbTheme('#7c3aed', '#ffffff', '#0f172a'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#7c3aed',
						'accent_grad' => 'linear-gradient(135deg, #06b6d4 0%, #8b5cf6 50%, #ec4899 100%)',
						'surface'     => '#ffffff',
						'surface_alt' => '#fafbff',
						'text'        => '#0f172a',
						'text_muted'  => '#475569',
						'border'      => '#e0e7ff',
						'focus_ring'  => '#7c3aed',
					],
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => 'Inter, system-ui, sans-serif',
						'scale'          => 1.0,
						'line_height'    => 1.6,
					],
					'radius' => 'md',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'sunset',
				'title_key'       => 'FLEXI_PRESET_THEME_SUNSET',
				'description_key' => 'FLEXI_PRESET_THEME_SUNSET_DESC',
				'group'           => 'modern',
				'thumbnail'       => self::thumbTheme('#c2410c', '#fffaf5', '#1c1917'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#c2410c',
						'accent_grad' => 'linear-gradient(135deg, #fb923c 0%, #f43f5e 60%, #a855f7 100%)',
						'surface'     => '#fffaf5',
						'surface_alt' => '#fff5eb',
						'text'        => '#1c1917',
						'text_muted'  => '#57534e',
						'border'      => '#fde4cd',
						'focus_ring'  => '#c2410c',
					],
					'typography' => [
						'family'         => 'Outfit, system-ui, sans-serif',
						'family_heading' => 'Outfit, system-ui, sans-serif',
						'scale'          => 1.0,
						'line_height'    => 1.6,
					],
					'radius' => 'md',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'ocean-glass',
				'title_key'       => 'FLEXI_PRESET_THEME_OCEAN_GLASS',
				'description_key' => 'FLEXI_PRESET_THEME_OCEAN_GLASS_DESC',
				'group'           => 'modern',
				// Body contrast 10.3:1 (#134e4a on #ffffff) — a11y-lead
				// corrected from initial "12:1" claim.
				'thumbnail'       => self::thumbTheme('#0e7490', '#ffffff', '#134e4a'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#0e7490',
						'accent_grad' => 'linear-gradient(135deg, #06b6d4 0%, #0e7490 50%, #1e40af 100%)',
						'surface'     => '#ffffff',
						'surface_alt' => '#f0fdfa',
						'text'        => '#134e4a',
						'text_muted'  => '#3f6359',
						'border'      => '#99f6e4',
						'focus_ring'  => '#0e7490',
					],
					'typography' => [
						'family'         => '"DM Sans", system-ui, sans-serif',
						'family_heading' => '"DM Sans", system-ui, sans-serif',
						'scale'          => 1.0,
						'line_height'    => 1.6,
					],
					'radius' => 'lg',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'cyber-neon',
				'title_key'       => 'FLEXI_PRESET_THEME_CYBER_NEON',
				'description_key' => 'FLEXI_PRESET_THEME_CYBER_NEON_DESC',
				'group'           => 'dark',
				'thumbnail'       => self::thumbTheme('#ec4899', '#0a0a0f', '#fafafa'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#ec4899',
						'accent_grad' => 'linear-gradient(135deg, #ec4899 0%, #06b6d4 100%)',
						'surface'     => '#0a0a0f',
						'surface_alt' => '#14141f',
						'text'        => '#fafafa',
						'text_muted'  => '#a3a3a3',
						'border'      => '#404040',
						// a11y-lead pinned focus ring to cyan (7.4:1 on
						// #0a0a0f) rather than the neon pink (5.2:1).
						'focus_ring'  => '#06b6d4',
					],
					'typography' => [
						// REQUIRED TWEAK: body stays sans (Inter). Monospace
						// at body sizes harms low-vision reading speed.
						// JetBrains Mono on heading only.
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => '"JetBrains Mono", ui-monospace, SFMono-Regular, monospace',
						'scale'          => 1.0,
						'line_height'    => 1.55,
					],
					'radius' => 'sm',
					'mode'   => 'dark',
				],
			],
			[
				'key'             => 'pastel-dream',
				'title_key'       => 'FLEXI_PRESET_THEME_PASTEL_DREAM',
				'description_key' => 'FLEXI_PRESET_THEME_PASTEL_DREAM_DESC',
				'group'           => 'minimal',
				'thumbnail'       => self::thumbTheme('#be185d', '#ffffff', '#500724'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#be185d',
						'accent_grad' => 'linear-gradient(135deg, #f472b6 0%, #c084fc 50%, #60a5fa 100%)',
						'surface'     => '#ffffff',
						'surface_alt' => '#fdf4ff',
						'text'        => '#500724',
						'text_muted'  => '#831843',
						'border'      => '#fbcfe8',
						'focus_ring'  => '#be185d',
					],
					'typography' => [
						'family'         => 'Quicksand, "Nunito Sans", system-ui, sans-serif',
						'family_heading' => 'Quicksand, "Nunito Sans", system-ui, sans-serif',
						'scale'          => 1.05,
						'line_height'    => 1.7,
					],
					// Larger soft corners; CSS overrides --fc-radius-card.
					'radius' => 'lg',
					'mode'   => 'light',
				],
			],
			[
				'key'             => 'monochrome-plus',
				'title_key'       => 'FLEXI_PRESET_THEME_MONOCHROME_PLUS',
				'description_key' => 'FLEXI_PRESET_THEME_MONOCHROME_PLUS_DESC',
				'group'           => 'minimal',
				'thumbnail'       => self::thumbTheme('#000000', '#ffffff', '#000000'),
				'theme_data'      => [
					'colors' => [
						'accent'      => '#000000',
						'accent_grad' => 'linear-gradient(135deg, #000000, #404040)',
						'surface'     => '#ffffff',
						'surface_alt' => '#fafafa',
						'text'        => '#000000',
						'text_muted'  => '#525252',
						'border'      => '#d4d4d4',
						'focus_ring'  => '#000000',
					],
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => 'Inter, system-ui, sans-serif',
						'scale'          => 1.0,
						'line_height'    => 1.6,
					],
					// CSS animates --fc-accent through 4 keyframes (14s,
					// behind prefers-reduced-motion: no-preference). Static
					// baseline is black so reduced-motion users see
					// canonical monochrome.
					'radius' => 'md',
					'mode'   => 'light',
				],
			],
		];
	}

	/* ---------------------------------------------------------------------
	 * SVG thumbnail factories
	 *
	 * Returned as raw <svg> strings, aria-hidden="true" + focusable="false"
	 * (decorative — the card's text label provides the accessible name).
	 * 240x150 canvas. All structural fills are >=3:1 against #f8fafc card bg
	 * to satisfy WCAG 1.4.11 even though the SVG is hidden from AT.
	 * Palette:
	 *   #0f172a slate-900  ~17:1   (titles, dark blocks)
	 *   #475569 slate-600  ~7.7:1  (body lines)
	 *   #64748b slate-500  ~4.4:1  (image placeholders, subtle blocks)
	 *   #2563eb blue-600   ~5.2:1  (accent stripes / pills)
	 * ------------------------------------------------------------------- */

	protected static function svgFrame(string $body): string
	{
		return '<svg viewBox="0 0 240 150" xmlns="http://www.w3.org/2000/svg" '
		     . 'class="fcpt-preset-thumb-svg" aria-hidden="true" focusable="false">'
		     . '<rect width="240" height="150" rx="6" fill="#f8fafc" stroke="#64748b"/>'
		     . $body
		     . '</svg>';
	}

	protected static function thumbItemMagazine(): string
	{
		return self::svgFrame(
			'<rect x="14" y="14" width="212" height="46" rx="3" fill="#64748b"/>'
			. '<rect x="14" y="68" width="170" height="9" rx="2" fill="#0f172a"/>'
			. '<rect x="14" y="84" width="138" height="5" fill="#475569"/>'
			. '<rect x="14" y="93" width="138" height="5" fill="#475569"/>'
			. '<rect x="14" y="102" width="120" height="5" fill="#475569"/>'
			. '<rect x="14" y="111" width="138" height="5" fill="#475569"/>'
			. '<rect x="14" y="120" width="100" height="5" fill="#475569"/>'
			. '<rect x="160" y="84" width="66" height="5" rx="1" fill="#2563eb"/>'
			. '<rect x="160" y="93" width="50" height="5" fill="#64748b"/>'
			. '<rect x="160" y="102" width="50" height="5" fill="#64748b"/>'
			. '<rect x="160" y="111" width="40" height="5" fill="#64748b"/>'
		);
	}

	protected static function thumbItemEditorial(): string
	{
		return self::svgFrame(
			'<rect x="60" y="14" width="34" height="7" rx="3" fill="#2563eb"/>'
			. '<rect x="60" y="26" width="120" height="11" rx="2" fill="#0f172a"/>'
			. '<rect x="60" y="42" width="60" height="5" fill="#64748b"/>'
			. '<rect x="60" y="56" width="120" height="6" fill="#475569"/>'
			. '<rect x="60" y="65" width="110" height="6" fill="#475569"/>'
			. '<rect x="14" y="80" width="105" height="5" fill="#475569"/>'
			. '<rect x="14" y="89" width="105" height="5" fill="#475569"/>'
			. '<rect x="14" y="98" width="105" height="5" fill="#475569"/>'
			. '<rect x="14" y="107" width="90" height="5" fill="#475569"/>'
			. '<rect x="128" y="80" width="98" height="50" rx="3" fill="#64748b"/>'
		);
	}

	protected static function thumbItemMedia(): string
	{
		return self::svgFrame(
			'<rect x="14" y="14" width="212" height="60" rx="3" fill="#64748b"/>'
			. '<rect x="14" y="80" width="212" height="14" rx="2" fill="#0f172a"/>'
			. '<rect x="22" y="85" width="100" height="4" fill="#f8fafc"/>'
			. '<rect x="14" y="102" width="62" height="32" rx="2" fill="#64748b"/>'
			. '<rect x="84" y="102" width="142" height="5" fill="#475569"/>'
			. '<rect x="84" y="111" width="142" height="5" fill="#475569"/>'
			. '<rect x="84" y="120" width="120" height="5" fill="#475569"/>'
			. '<rect x="84" y="129" width="142" height="5" fill="#475569"/>'
		);
	}

	protected static function thumbItemCompact(): string
	{
		return self::svgFrame(
			'<rect x="20" y="20" width="140" height="9" rx="2" fill="#0f172a"/>'
			. '<rect x="20" y="36" width="56" height="5" fill="#64748b"/>'
			. '<rect x="82" y="36" width="44" height="5" fill="#64748b"/>'
			. '<rect x="20" y="50" width="200" height="1" fill="#64748b"/>'
			. '<rect x="20" y="60" width="200" height="5" fill="#475569"/>'
			. '<rect x="20" y="70" width="200" height="5" fill="#475569"/>'
			. '<rect x="20" y="80" width="180" height="5" fill="#475569"/>'
			. '<rect x="20" y="90" width="200" height="5" fill="#475569"/>'
			. '<rect x="20" y="100" width="170" height="5" fill="#475569"/>'
			. '<rect x="20" y="110" width="200" height="5" fill="#475569"/>'
			. '<rect x="20" y="120" width="120" height="5" fill="#475569"/>'
		);
	}

	protected static function thumbCatGrid(): string
	{
		return self::svgFrame(
			'<rect x="14" y="14" width="160" height="10" rx="2" fill="#0f172a"/>'
			. '<rect x="14" y="30" width="200" height="5" fill="#64748b"/>'
			. '<rect x="14" y="50" width="66" height="40" rx="3" fill="#64748b"/>'
			. '<rect x="14" y="93" width="50" height="4" fill="#0f172a"/>'
			. '<rect x="14" y="101" width="40" height="3" fill="#475569"/>'
			. '<rect x="87" y="50" width="66" height="40" rx="3" fill="#64748b"/>'
			. '<rect x="87" y="93" width="50" height="4" fill="#0f172a"/>'
			. '<rect x="87" y="101" width="40" height="3" fill="#475569"/>'
			. '<rect x="160" y="50" width="66" height="40" rx="3" fill="#64748b"/>'
			. '<rect x="160" y="93" width="50" height="4" fill="#0f172a"/>'
			. '<rect x="160" y="101" width="40" height="3" fill="#475569"/>'
			. '<rect x="14" y="115" width="66" height="20" rx="3" fill="#475569"/>'
			. '<rect x="87" y="115" width="66" height="20" rx="3" fill="#475569"/>'
			. '<rect x="160" y="115" width="66" height="20" rx="3" fill="#475569"/>'
		);
	}

	protected static function thumbCatFeatured(): string
	{
		return self::svgFrame(
			'<rect x="14" y="14" width="180" height="9" rx="2" fill="#0f172a"/>'
			. '<rect x="14" y="28" width="160" height="5" fill="#64748b"/>'
			. '<rect x="14" y="42" width="124" height="56" rx="3" fill="#64748b"/>'
			. '<rect x="146" y="50" width="80" height="7" rx="2" fill="#0f172a"/>'
			. '<rect x="146" y="62" width="80" height="4" fill="#475569"/>'
			. '<rect x="146" y="70" width="70" height="4" fill="#475569"/>'
			. '<rect x="146" y="78" width="60" height="4" fill="#475569"/>'
			. '<rect x="14" y="108" width="212" height="9" rx="2" fill="#475569"/>'
			. '<rect x="14" y="122" width="212" height="9" rx="2" fill="#475569"/>'
		);
	}

	protected static function thumbCatMagazine(): string
	{
		return self::svgFrame(
			'<rect x="14" y="14" width="212" height="18" rx="2" fill="#0f172a"/>'
			. '<rect x="22" y="20" width="80" height="6" fill="#f8fafc"/>'
			. '<rect x="14" y="40" width="140" height="46" rx="3" fill="#64748b"/>'
			. '<rect x="160" y="40" width="66" height="46" rx="3" fill="#64748b"/>'
			. '<rect x="14" y="92" width="66" height="32" rx="3" fill="#64748b"/>'
			. '<rect x="87" y="92" width="66" height="32" rx="3" fill="#64748b"/>'
			. '<rect x="160" y="92" width="66" height="32" rx="3" fill="#64748b"/>'
			. '<rect x="14" y="130" width="212" height="4" fill="#475569"/>'
		);
	}

	protected static function thumbCatCompact(): string
	{
		return self::svgFrame(
			'<rect x="20" y="18" width="140" height="9" rx="2" fill="#0f172a"/>'
			. '<rect x="20" y="32" width="200" height="4" fill="#64748b"/>'
			. '<rect x="20" y="44" width="200" height="1" fill="#64748b"/>'
			. '<rect x="20" y="54" width="200" height="6" rx="1" fill="#475569"/>'
			. '<rect x="20" y="64" width="200" height="6" rx="1" fill="#475569"/>'
			. '<rect x="20" y="74" width="200" height="6" rx="1" fill="#475569"/>'
			. '<rect x="20" y="84" width="200" height="6" rx="1" fill="#475569"/>'
			. '<rect x="20" y="94" width="200" height="6" rx="1" fill="#475569"/>'
			. '<rect x="20" y="104" width="200" height="6" rx="1" fill="#475569"/>'
			. '<rect x="20" y="114" width="200" height="6" rx="1" fill="#475569"/>'
			. '<rect x="20" y="124" width="200" height="6" rx="1" fill="#475569"/>'
		);
	}

	protected static function thumbItemPortfolio(): string
	{
		return self::svgFrame(
			'<rect x="14" y="14" width="212" height="50" rx="3" fill="#64748b"/>'
			. '<rect x="14" y="70" width="140" height="9" rx="2" fill="#0f172a"/>'
			. '<rect x="14" y="82" width="40" height="5" rx="2" fill="#2563eb"/>'
			. '<rect x="180" y="70" width="46" height="5" fill="#475569"/>'
			. '<rect x="180" y="78" width="46" height="5" fill="#475569"/>'
			. '<rect x="14" y="94" width="100" height="40" rx="3" fill="#64748b"/>'
			. '<rect x="122" y="94" width="104" height="6" fill="#475569"/>'
			. '<rect x="122" y="104" width="104" height="6" fill="#475569"/>'
			. '<rect x="122" y="114" width="80" height="6" fill="#475569"/>'
			. '<rect x="122" y="124" width="104" height="6" fill="#475569"/>'
		);
	}

	protected static function thumbItemLongform(): string
	{
		return self::svgFrame(
			'<rect x="56" y="14" width="32" height="6" rx="2" fill="#2563eb"/>'
			. '<rect x="56" y="24" width="128" height="11" rx="2" fill="#0f172a"/>'
			. '<rect x="56" y="40" width="128" height="4" fill="#475569"/>'
			. '<rect x="56" y="48" width="116" height="4" fill="#475569"/>'
			. '<rect x="56" y="60" width="128" height="1" fill="#64748b"/>'
			. '<rect x="56" y="68" width="40" height="3" fill="#64748b"/>'
			. '<rect x="100" y="68" width="40" height="3" fill="#64748b"/>'
			. '<rect x="56" y="80" width="128" height="30" rx="2" fill="#64748b"/>'
			. '<rect x="56" y="116" width="128" height="4" fill="#475569"/>'
			. '<rect x="56" y="124" width="120" height="4" fill="#475569"/>'
			. '<rect x="56" y="132" width="128" height="4" fill="#475569"/>'
		);
	}

	protected static function thumbCatMasonry(): string
	{
		return self::svgFrame(
			'<rect x="14" y="14" width="70" height="56" rx="3" fill="#64748b"/>'
			. '<rect x="14" y="74" width="40" height="4" rx="1" fill="#2563eb"/>'
			. '<rect x="14" y="82" width="60" height="6" fill="#0f172a"/>'
			. '<rect x="14" y="92" width="60" height="3" fill="#475569"/>'
			. '<rect x="92" y="14" width="70" height="38" rx="3" fill="#64748b"/>'
			. '<rect x="92" y="56" width="60" height="6" fill="#0f172a"/>'
			. '<rect x="92" y="66" width="60" height="3" fill="#475569"/>'
			. '<rect x="92" y="74" width="60" height="3" fill="#475569"/>'
			. '<rect x="170" y="14" width="56" height="70" rx="3" fill="#64748b"/>'
			. '<rect x="170" y="88" width="56" height="6" fill="#0f172a"/>'
			. '<rect x="14" y="105" width="70" height="30" rx="3" fill="#64748b"/>'
			. '<rect x="92" y="92" width="70" height="42" rx="3" fill="#64748b"/>'
			. '<rect x="170" y="100" width="56" height="34" rx="3" fill="#64748b"/>'
		);
	}

	protected static function thumbCatNewsfeed(): string
	{
		return self::svgFrame(
			'<rect x="14" y="18" width="30" height="14" rx="7" fill="#2563eb"/>'
			. '<rect x="52" y="18" width="120" height="7" rx="2" fill="#0f172a"/>'
			. '<rect x="52" y="29" width="160" height="3" fill="#475569"/>'
			. '<rect x="52" y="35" width="140" height="3" fill="#475569"/>'
			. '<rect x="14" y="48" width="200" height="1" fill="#64748b"/>'
			. '<rect x="14" y="58" width="30" height="14" rx="7" fill="#2563eb"/>'
			. '<rect x="52" y="58" width="120" height="7" rx="2" fill="#0f172a"/>'
			. '<rect x="52" y="69" width="160" height="3" fill="#475569"/>'
			. '<rect x="52" y="75" width="120" height="3" fill="#475569"/>'
			. '<rect x="14" y="88" width="200" height="1" fill="#64748b"/>'
			. '<rect x="14" y="98" width="30" height="14" rx="7" fill="#2563eb"/>'
			. '<rect x="52" y="98" width="120" height="7" rx="2" fill="#0f172a"/>'
			. '<rect x="52" y="109" width="160" height="3" fill="#475569"/>'
			. '<rect x="14" y="124" width="200" height="1" fill="#64748b"/>'
		);
	}

	protected static function thumbTheme(string $accent, string $surface, string $text): string
	{
		$a = htmlspecialchars($accent,  ENT_QUOTES, 'UTF-8');
		$s = htmlspecialchars($surface, ENT_QUOTES, 'UTF-8');
		$t = htmlspecialchars($text,    ENT_QUOTES, 'UTF-8');

		$body = '<rect width="240" height="150" rx="6" fill="' . $s . '" stroke="#64748b"/>'
		      . '<rect x="0" y="0" width="240" height="14" fill="' . $a . '"/>'
		      . '<rect x="18" y="30" width="140" height="10" rx="2" fill="' . $t . '"/>'
		      . '<rect x="18" y="50" width="200" height="4" rx="1" fill="' . $t . '" opacity="0.75"/>'
		      . '<rect x="18" y="60" width="180" height="4" rx="1" fill="' . $t . '" opacity="0.75"/>'
		      . '<rect x="18" y="70" width="200" height="4" rx="1" fill="' . $t . '" opacity="0.75"/>'
		      . '<rect x="18" y="80" width="160" height="4" rx="1" fill="' . $t . '" opacity="0.75"/>'
		      . '<rect x="18" y="100" width="58" height="20" rx="3" fill="' . $a . '"/>'
		      . '<rect x="82" y="100" width="36" height="20" rx="10" fill="' . $a . '" opacity="0.30"/>';

		return '<svg viewBox="0 0 240 150" xmlns="http://www.w3.org/2000/svg" '
		     . 'class="fcpt-preset-thumb-svg" aria-hidden="true" focusable="false">'
		     . $body
		     . '</svg>';
	}
}
