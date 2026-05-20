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
		// 4 original + portfolio + longform (added 2026-05-20) = 6.
		$this->assertCount(6, $presets, 'Item scope should expose 6 presets');
	}

	public function testCategoryPresetsCount(): void
	{
		$presets = \FlexicontentProTemplatePresetLibrary::getLayoutPresets('category');
		// 4 original + masonry + newsfeed (added 2026-05-20) = 6.
		$this->assertCount(6, $presets, 'Category scope should expose 6 presets');
	}

	public function testThemePresetsCount(): void
	{
		$themes = \FlexicontentProTemplatePresetLibrary::getThemePresets();
		// 6 original + 3 added in 6.1.0-beta.4 (CSS variety + font config)
		// + 6 expressive themes added in 6.1.0-beta.9 (aurora, sunset,
		// ocean-glass, cyber-neon, pastel-dream, monochrome-plus) = 15.
		$this->assertCount(15, $themes, 'Theme library should expose 15 presets after the v3 expressive theme ship.');
	}

	public function testV3ExpressiveThemesPresent(): void
	{
		$themes = \FlexicontentProTemplatePresetLibrary::getThemePresets();
		$keys   = array_column($themes, 'key');
		foreach (['aurora','sunset','ocean-glass','cyber-neon','pastel-dream','monochrome-plus'] as $expected) {
			$this->assertContains(
				$expected,
				$keys,
				"v3 expressive theme '$expected' missing from PresetLibrary — CSS selector orphaned"
			);
		}
	}

	public function testV3ThemesShipFocusRingTokenPinnedToAccent(): void
	{
		// a11y-lead required tweak: focus ring must use accent colour
		// (not a gradient stop) so 3:1 against surface is guaranteed.
		$themes = \FlexicontentProTemplatePresetLibrary::getThemePresets();
		$v3 = ['aurora','sunset','ocean-glass','pastel-dream','monochrome-plus'];
		foreach ($themes as $t) {
			if (!in_array($t['key'], $v3, true)) continue;
			$this->assertArrayHasKey('focus_ring', $t['theme_data']['colors'],
				"theme {$t['key']} must declare focus_ring");
			$this->assertSame(
				$t['theme_data']['colors']['accent'],
				$t['theme_data']['colors']['focus_ring'],
				"theme {$t['key']} focus_ring must equal accent (a11y-lead tweak)"
			);
		}
		// Cyber Neon is the documented exception — focus ring uses cyan
		// (#06b6d4, 7.4:1 on #0a0a0f), not pink (5.2:1).
		foreach ($themes as $t) {
			if ($t['key'] !== 'cyber-neon') continue;
			$this->assertSame('#06b6d4', $t['theme_data']['colors']['focus_ring'],
				'cyber-neon focus_ring must be cyan (higher contrast than pink accent)');
		}
	}

	public function testV3ThemesShipAccentGradient(): void
	{
		$themes = \FlexicontentProTemplatePresetLibrary::getThemePresets();
		$v3 = ['aurora','sunset','ocean-glass','cyber-neon','pastel-dream','monochrome-plus'];
		foreach ($themes as $t) {
			if (!in_array($t['key'], $v3, true)) continue;
			$this->assertArrayHasKey('accent_grad', $t['theme_data']['colors'],
				"theme {$t['key']} must ship an accent_grad token for the gradient-text + scrim patterns");
		}
	}

	public function testRendererInlineThemeStyleHelpersExist(): void
	{
		// Custom Theme bug fix (beta.12): Renderer must build inline
		// CSS custom properties from theme_data so user-authored Custom
		// Themes work without a matching CSS [data-fcpt-theme="..."]
		// rule. Pattern lifted from fieldlayout LayoutRenderer.
		$src = file_get_contents(dirname(__DIR__, 3)
			. '/admin/helpers/protemplate/Renderer.php');
		$this->assertNotFalse($src);
		$this->assertStringContainsString('protected function buildThemeStyleAttribute', $src);
		$this->assertStringContainsString('protected function resolveThemeData', $src);
		$this->assertStringContainsString('protected function resolveThemeKey', $src);
		$this->assertStringContainsString('protected function loadThemeRow', $src);
		// Inline style must be appended to the wrapper div.
		$this->assertStringContainsString('$themeStyle . \'>\'', $src);
	}

	public function testCyberNeonBodyFontIsNotMonospace(): void
	{
		// a11y-lead required tweak: monospace at body sizes harms
		// low-vision reading speed. JetBrains Mono on heading only.
		$theme = \FlexicontentProTemplatePresetLibrary::getThemePreset('cyber-neon');
		$this->assertNotNull($theme, 'cyber-neon theme must be findable by key');
		$body = strtolower($theme['theme_data']['typography']['family']);
		$this->assertStringNotContainsString('monospace', $body);
		$this->assertStringNotContainsString('jetbrains', $body);
		$this->assertStringContainsString('inter', $body, 'cyber-neon body must be Inter (sans), not mono');
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

	/* Required element coverage ---------------------------------------- *
	 *
	 * Every shipped preset must carry the four content elements that every
	 * real FLEXIcontent item provides: title, introtext, an image (intro or
	 * full), and a published date (created). Without these the chooser
	 * screen ships layouts that render empty cards for users who pick them
	 * without further editing — defeating the point of starter presets.
	 *
	 * Added 6.1.0-beta.7 after user feedback: "ระบบ layout ตัวอย่างควรใส่
	 * element ที่มีแน่นอนลงไปเลย title introtext images วันที่เผยแพร่ ใส่ไปเลย".
	 *
	 * ----------------------------------------------------------------- */

	/**
	 * Walk a layout and collect every `name` from `article`-type elements.
	 */
	private function collectArticleNames(array $layout): array
	{
		$names = [];
		foreach (($layout['sections'] ?? []) as $section) {
			foreach (($section['rows'] ?? []) as $row) {
				foreach (($row['cols'] ?? []) as $col) {
					foreach (($col['elements'] ?? []) as $el) {
						if (($el['type'] ?? '') === 'article' && isset($el['name'])) {
							$names[] = $el['name'];
						}
					}
				}
			}
		}
		return $names;
	}

	public function testEveryPresetDeclaresTitle(): void
	{
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$names = $this->collectArticleNames($p['layout']);
				$this->assertContains('title', $names, "Preset {$p['key']} missing required 'title' element");
			}
		}
	}

	public function testEveryPresetDeclaresIntrotext(): void
	{
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$names = $this->collectArticleNames($p['layout']);
				$this->assertContains('introtext', $names, "Preset {$p['key']} missing required 'introtext' element");
			}
		}
	}

	public function testEveryPresetDeclaresImage(): void
	{
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$names = $this->collectArticleNames($p['layout']);
				$hasImage = in_array('image_intro', $names, true) || in_array('image_full', $names, true);
				$this->assertTrue(
					$hasImage,
					"Preset {$p['key']} missing required image element (image_intro or image_full)"
				);
			}
		}
	}

	public function testEveryPresetDeclaresPublishDate(): void
	{
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$names = $this->collectArticleNames($p['layout']);
				$hasDate = in_array('created', $names, true) || in_array('publish_up', $names, true);
				$this->assertTrue(
					$hasDate,
					"Preset {$p['key']} missing required publish-date element (created or publish_up)"
				);
			}
		}
	}

	/**
	 * Regression guard for the 6.1.0-beta.6 → beta.8 incident: category
	 * presets shipped with text()-only mockup placeholders ("Card slot",
	 * "Featured story", "Items list renders here") instead of real article
	 * element references. Layouts created from those presets rendered as
	 * empty teaser cards on the frontend because renderText() output is
	 * inert template copy with no content binding.
	 */
	public function testNoStaleMockupPlaceholders(): void
	{
		$bannedPhrases = [
			'Card slot',
			'Card slot 1',
			'Card slot 2',
			'Card slot 3',
			'Featured story',
			'Featured items',
			'Items grid renders here',
			'More from this category',
			'Items list renders below',
			'Primary feature renders here',
			'Editor pick',
			'Lead story',
			'Secondary item slot',
			'Dense items list renders here',
			'Top item from category renders here',
		];

		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$flat = json_encode($p['layout']);
				foreach ($bannedPhrases as $phrase) {
					$this->assertStringNotContainsString(
						$phrase,
						$flat,
						"Preset {$p['key']} contains stale mockup placeholder: \"$phrase\""
					);
				}
			}
		}
	}

	/**
	 * Category presets must NOT carry stand-alone text() elements at all —
	 * they exist for per-item teaser rendering where every element should
	 * bind to item data (article type), not emit static copy. text() is
	 * fine in item-scope presets (e.g. demo paragraphs) but in category
	 * scope it produces dead placeholder output on every card.
	 */
	public function testCategoryPresetsContainNoTextElements(): void
	{
		foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets('category') as $p) {
			$textCount = 0;
			foreach (($p['layout']['sections'] ?? []) as $sec) {
				foreach (($sec['rows'] ?? []) as $row) {
					foreach (($row['cols'] ?? []) as $col) {
						foreach (($col['elements'] ?? []) as $el) {
							if (($el['type'] ?? '') === 'text') { $textCount++; }
						}
					}
				}
			}
			$this->assertSame(
				0,
				$textCount,
				"Category preset {$p['key']} must use article elements only — found {$textCount} text() placeholder element(s)"
			);
		}
	}

	/**
	 * Every preset must produce a non-empty rendered tree once the Renderer
	 * walks it: at least one section, at least one row in each section, at
	 * least one column per row, at least one element per column. Empty
	 * sub-trees are silently dropped by the Renderer (returns ''), so an
	 * empty section ships as a blank frontend card.
	 */
	public function testEveryPresetTreeIsNonEmpty(): void
	{
		foreach (['item', 'category'] as $scope) {
			foreach (\FlexicontentProTemplatePresetLibrary::getLayoutPresets($scope) as $p) {
				$sections = $p['layout']['sections'] ?? [];
				$this->assertNotEmpty($sections, "Preset {$p['key']} has no sections");

				foreach ($sections as $i => $sec) {
					$rows = $sec['rows'] ?? [];
					$this->assertNotEmpty($rows, "Preset {$p['key']} section {$i} has no rows");

					foreach ($rows as $j => $row) {
						$cols = $row['cols'] ?? [];
						$this->assertNotEmpty($cols, "Preset {$p['key']} section {$i} row {$j} has no cols");

						foreach ($cols as $k => $col) {
							$els = $col['elements'] ?? [];
							$this->assertNotEmpty(
								$els,
								"Preset {$p['key']} section {$i} row {$j} col {$k} has no elements"
							);
						}
					}
				}
			}
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
