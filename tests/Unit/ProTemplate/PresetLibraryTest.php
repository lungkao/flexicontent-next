<?php
/**
 * Unit tests for the Pro Templates / Pro Themes Preset Library shipped in
 * 6.1.0-beta.2. Library presets feed the chooser screen and the model's
 * createFromPreset() method, so wrong shape = broken UI + broken inserts.
 *
 * Covers:
 *   1. Item / category / theme catalogue counts
 *   2. Required keys on every layout + theme preset
 *   3. Canonical layout shape (sections > rows > cols > elements)
 *   4. theme_data shape (colors, typography, radius, mode)
 *   5. Lookup-by-key + null on unknown
 *   6. Scope clamp falls back to 'item' presets
 *   7. SVG thumbnails are decorative (aria-hidden + focusable=false)
 *   8. SVG fills stay within the contrast-checked palette
 *   9. Group registry references + key uniqueness
 *
 * @package FLEXIcontent
 * @since   6.1.0-beta.2
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class PresetLibraryTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 3) . '/admin/helpers/protemplate/PresetLibrary.php';
	}

	/* Counts ----------------------------------------------------------- */

	public function testItemPresetsCount(): void
	{
		$presets = \FlexicontentProTemplatePresetLibrary::getLayoutPresets('item');
		$this->assertCount(4, $presets, 'Item scope should expose 4 presets');
	}

	public function testCategoryPresetsCount(): void
	{
		$presets = \FlexicontentProTemplatePresetLibrary::getLayoutPresets('category');
		$this->assertCount(4, $presets, 'Category scope should expose 4 presets');
	}

	public function testThemePresetsCount(): void
	{
		$themes = \FlexicontentProTemplatePresetLibrary::getThemePresets();
		$this->assertCount(9, $themes, 'Theme library should expose 9 presets (6 original + 3 added in 6.1.0-beta.4 for CSS variety + font config).');
	}

	public function testThemePresetsCoverDistinctHeadingFontFamilies(): void
	{
		$themes = \FlexicontentProTemplatePresetLibrary::getThemePresets();

		$families = [];
		foreach ($themes as $t) {
			$families[] = strtolower($t['theme_data']['typography']['family_heading']);
		}

		$unique = array_unique($families);

		// We require at least 4 distinct heading font stacks across the
		// library so users have real typographic variety (sans, serif,
		// display serif, monospace) — not just color swaps.
		$this->assertGreaterThanOrEqual(
			4,
			count($unique),
			'Theme library must expose at least 4 distinct heading font stacks for typographic variety.'
		);
	}

	public function testThemePresetsAdvertiseSerifMonoAndSansHeadings(): void
	{
		$themes = \FlexicontentProTemplatePresetLibrary::getThemePresets();

		$hasSerif = false;
		$hasMono  = false;
		$hasSans  = false;

		foreach ($themes as $t) {
			$h = strtolower($t['theme_data']['typography']['family_heading']);
			if (str_contains($h, 'serif') || str_contains($h, 'georgia') || str_contains($h, 'garamond') || str_contains($h, 'playfair')) {
				$hasSerif = true;
			}
			if (str_contains($h, 'mono') || str_contains($h, 'jetbrains')) {
				$hasMono = true;
			}
			if (str_contains($h, 'inter') || str_contains($h, 'system-ui') || str_contains($h, 'quicksand') || str_contains($h, 'sans-serif')) {
				$hasSans = true;
			}
		}

		$this->assertTrue($hasSerif, 'Theme library must include at least one serif heading family.');
		$this->assertTrue($hasMono,  'Theme library must include at least one monospace heading family.');
		$this->assertTrue($hasSans,  'Theme library must include at least one sans-serif heading family.');
	}

	public function testScopeClampFallsBackToItem(): void
	{
		$valid   = \FlexicontentProTemplatePresetLibrary::getLayoutPresets('item');
		$garbage = \FlexicontentProTemplatePresetLibrary::getLayoutPresets('floppy-disk');
		$this->assertSame(
			array_column($valid,   'key'),
			array_column($garbage, 'key'),
			'Unknown scope must clamp to item presets'
		);
	}

	/* Layout shape ----------------------------------------------------- */

	public function testLayoutPresetsDeclareRequiredKeys(): void
	{
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				foreach (['key', 'scope', 'title_key', 'description_key', 'group', 'thumbnail', 'layout'] as $k) {
					$this->assertArrayHasKey($k, $p, "Preset missing $k");
				}
				$this->assertSame($scope, $p['scope'], "Preset scope mismatch on {$p['key']}");
				$this->assertNotSame('',  $p['key']);
				$this->assertNotSame('',  $p['title_key']);
				$this->assertNotSame('',  $p['thumbnail']);
				$this->assertIsArray($p['layout']);
			}
		}
	}

	public function testLayoutHasCanonicalShape(): void
	{
		$p = \FlexicontentProTemplatePresetLibrary::getLayoutPreset('item-magazine');
		$this->assertNotNull($p);

		$layout = $p['layout'];
		$this->assertArrayHasKey('version',  $layout);
		$this->assertArrayHasKey('settings', $layout);
		$this->assertArrayHasKey('sections', $layout);
		$this->assertSame(2, $layout['version']);

		foreach (['theme', 'width', 'spacing', 'themeId'] as $sk) {
			$this->assertArrayHasKey($sk, $layout['settings']);
		}

		$this->assertNotEmpty($layout['sections']);

		foreach ($layout['sections'] as $section) {
			foreach (['id', 'label', 'appearance', 'rows'] as $sk) {
				$this->assertArrayHasKey($sk, $section, "Section missing $sk");
			}
			foreach ($section['rows'] as $row) {
				foreach (['id', 'appearance', 'cols'] as $rk) {
					$this->assertArrayHasKey($rk, $row, "Row missing $rk");
				}
				foreach ($row['cols'] as $col) {
					foreach (['id', 'width', 'elements'] as $ck) {
						$this->assertArrayHasKey($ck, $col, "Col missing $ck");
					}
					$this->assertGreaterThanOrEqual(2, $col['width']);
					$this->assertLessThanOrEqual(12,   $col['width']);
					foreach ($col['elements'] as $el) {
						$this->assertArrayHasKey('type', $el);
						$this->assertArrayHasKey('_uid', $el, 'Every element needs a stable _uid');
					}
				}
			}
		}
	}

	/* Theme shape ------------------------------------------------------ */

	public function testThemePresetsDeclareRequiredKeys(): void
	{
		foreach (\FlexicontentProTemplatePresetLibrary::getThemePresets() as $t) {
			foreach (['key', 'title_key', 'description_key', 'group', 'thumbnail', 'theme_data'] as $k) {
				$this->assertArrayHasKey($k, $t, "Theme missing $k");
			}

			$td = $t['theme_data'];
			foreach (['colors', 'typography', 'radius', 'mode'] as $tk) {
				$this->assertArrayHasKey($tk, $td, "theme_data missing $tk on {$t['key']}");
			}
			foreach (['accent', 'surface', 'surface_alt', 'text', 'text_muted', 'border'] as $ck) {
				$this->assertArrayHasKey($ck, $td['colors'], "theme_data.colors missing $ck");
				$this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{3,8}$/', $td['colors'][$ck]);
			}
			foreach (['family', 'family_heading', 'scale', 'line_height'] as $tk) {
				$this->assertArrayHasKey($tk, $td['typography'], "theme_data.typography missing $tk");
			}
			$this->assertContains($td['radius'], ['sm', 'md', 'lg', 'pill']);
			$this->assertContains($td['mode'],   ['light', 'dark']);
		}
	}

	/* Lookups ---------------------------------------------------------- */

	public function testGetLayoutPresetFindsByKey(): void
	{
		$p = \FlexicontentProTemplatePresetLibrary::getLayoutPreset('cat-grid');
		$this->assertNotNull($p);
		$this->assertSame('cat-grid', $p['key']);
		$this->assertSame('category', $p['scope']);
	}

	public function testGetLayoutPresetReturnsNullForUnknown(): void
	{
		$this->assertNull(\FlexicontentProTemplatePresetLibrary::getLayoutPreset('does-not-exist'));
	}

	public function testGetThemePresetFindsByKey(): void
	{
		$t = \FlexicontentProTemplatePresetLibrary::getThemePreset('dark-pro');
		$this->assertNotNull($t);
		$this->assertSame('dark', $t['theme_data']['mode']);
	}

	public function testGetThemePresetReturnsNullForUnknown(): void
	{
		$this->assertNull(\FlexicontentProTemplatePresetLibrary::getThemePreset('does-not-exist'));
	}

	/* SVG thumbnails — a11y + contrast --------------------------------- */

	public function testThumbnailsAreDecorativeSvg(): void
	{
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$svg = $p['thumbnail'];
				$this->assertStringStartsWith('<svg', $svg, "Thumbnail must be an <svg> on {$p['key']}");
				$this->assertStringContainsString('aria-hidden="true"', $svg,
					"Thumbnail must carry aria-hidden=true on {$p['key']}");
				$this->assertStringContainsString('focusable="false"', $svg,
					"Thumbnail must carry focusable=false on {$p['key']}");
			}
		}

		foreach (\FlexicontentProTemplatePresetLibrary::getThemePresets() as $t) {
			$svg = $t['thumbnail'];
			$this->assertStringContainsString('aria-hidden="true"', $svg,
				"Theme thumbnail must be decorative on {$t['key']}");
			$this->assertStringContainsString('focusable="false"', $svg,
				"Theme thumbnail must declare focusable=false on {$t['key']}");
		}
	}

	public function testLayoutThumbnailsUseApprovedPalette(): void
	{
		// WCAG 1.4.11: structural fills must clear 3:1 against #f8fafc bg.
		// Banned (fail 3:1 on the f8fafc card background):
		//   #cbd5e1 (slate-300, ~1.4:1)
		//   #e2e8f0 (slate-200)
		$banned = ['#cbd5e1', '#e2e8f0'];

		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				foreach ($banned as $color) {
					$this->assertStringNotContainsString(
						'fill="' . $color . '"',
						$p['thumbnail'],
						"Thumbnail {$p['key']} uses banned low-contrast fill $color"
					);
				}
			}
		}
	}

	/* Group registry --------------------------------------------------- */

	public function testLayoutGroupsStartWithAll(): void
	{
		$groups = \FlexicontentProTemplatePresetLibrary::getLayoutGroups();
		$this->assertNotEmpty($groups);
		$this->assertSame('all', $groups[0]['key'], 'First layout group must be "all"');
	}

	public function testThemeGroupsStartWithAll(): void
	{
		$groups = \FlexicontentProTemplatePresetLibrary::getThemeGroups();
		$this->assertNotEmpty($groups);
		$this->assertSame('all', $groups[0]['key']);
	}

	public function testEveryLayoutPresetGroupIsRegistered(): void
	{
		$groupKeys = array_column(\FlexicontentProTemplatePresetLibrary::getLayoutGroups(), 'key');
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$this->assertContains(
					$p['group'],
					$groupKeys,
					"Preset {$p['key']} references unknown group '{$p['group']}'"
				);
			}
		}
	}

	public function testEveryThemePresetGroupIsRegistered(): void
	{
		$groupKeys = array_column(\FlexicontentProTemplatePresetLibrary::getThemeGroups(), 'key');
		foreach (\FlexicontentProTemplatePresetLibrary::getThemePresets() as $t) {
			$this->assertContains(
				$t['group'],
				$groupKeys,
				"Theme {$t['key']} references unknown group '{$t['group']}'"
			);
		}
	}

	/* Unique keys ------------------------------------------------------ */

	public function testLayoutPresetKeysAreUnique(): void
	{
		$keys = [];
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$keys[] = $p['key'];
			}
		}
		$this->assertSame(count($keys), count(array_unique($keys)), 'Preset keys must be globally unique');
	}

	public function testThemePresetKeysAreUnique(): void
	{
		$keys = array_column(\FlexicontentProTemplatePresetLibrary::getThemePresets(), 'key');
		$this->assertSame(count($keys), count(array_unique($keys)));
	}
}
