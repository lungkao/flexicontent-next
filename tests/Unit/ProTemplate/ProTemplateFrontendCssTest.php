<?php
/**
 * Regression guards for the Pro Templates frontend stylesheet shipped
 * in 6.1.0-beta.8+.
 *
 * The Pro Layout category teaser feed emits HTML with classes
 *   .fc-cat-pro-list / .fc-cat-pro-li / .fcpt-image-intro / .fcpt-title /
 *   .fcpt-created / .fcpt-introtext
 * but the stylesheet to render those as a card grid only existed after
 * the screenshot-reported "ui ไม่สวยเลย" regression. These tests pin:
 *
 *   • file exists at the expected URL path
 *   • carries the key class-name rules used by the Renderer output
 *   • declares accessibility-critical patterns (focus-visible ring,
 *     prefers-reduced-motion guard, line-clamp without removing DOM text)
 *   • the category view registers it via WebAssetManager — both default
 *     and mcats Pro-Layout branches.
 *
 * A11y-cleared (accessibility-lead, 2026-05-19) — GO with focus-ring
 * 3:1+ token, ::after card-overlay link, no separate "Read more".
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.8
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class ProTemplateFrontendCssTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	private function cssPath(): string
	{
		return $this->root() . '/site/assets/css/protemplate_frontend.css';
	}

	private function css(): string
	{
		$src = file_get_contents($this->cssPath());
		$this->assertNotFalse($src, 'protemplate_frontend.css missing or unreadable');
		return $src;
	}

	public function testStylesheetExists(): void
	{
		$this->assertFileExists($this->cssPath(),
			'Pro Layout frontend stylesheet must ship in site/assets/css/');
	}

	public function testCarriesCoreCardClasses(): void
	{
		$src = $this->css();
		// Renderer + category view emit these. Removing any rule below
		// re-introduces the "bullets visible / no card grid" regression.
		$this->assertStringContainsString('.fc-cat-pro-list', $src);
		$this->assertStringContainsString('.fc-cat-pro-li',   $src);
		$this->assertStringContainsString('.fc-mcats-pro-list', $src);
		$this->assertStringContainsString('.fc-mcats-pro-li',   $src);
		$this->assertStringContainsString('.fcpt-title',     $src);
		$this->assertStringContainsString('.fcpt-image-intro', $src);
		$this->assertStringContainsString('.fcpt-introtext', $src);
	}

	public function testRemovesUlBullets(): void
	{
		// Regression: without list-style:none, the <ul class="fc-cat-pro-list">
		// shows browser-default bullets on every card.
		$this->assertMatchesRegularExpression(
			'#\.fc-cat-pro-list[^{]*\{[^}]*list-style\s*:\s*none#s',
			$this->css(),
			'fc-cat-pro-list must reset list-style to none'
		);
	}

	public function testHasResponsiveGrid(): void
	{
		// Cards must reflow at narrow viewports without a media query —
		// auto-fill + minmax gives single-column fallback automatically.
		$this->assertMatchesRegularExpression(
			'#grid-template-columns\s*:\s*repeat\(\s*auto-fill\s*,\s*minmax\(\s*\d+px\s*,\s*1fr\s*\)\s*\)#',
			$this->css(),
			'card grid must use auto-fill minmax(...,1fr) so DOM order == visual order'
		);
	}

	public function testFocusVisibleRing(): void
	{
		// WCAG 2.4.11 / 2.4.13 — visible focus indicator on card link.
		$css = $this->css();
		$this->assertStringContainsString(':focus-visible', $css,
			'must use :focus-visible (not :focus) to avoid mouse-click rings');
		$this->assertMatchesRegularExpression(
			'#--fc-focus-ring\s*:#',
			$css,
			'must define --fc-focus-ring token for both color schemes'
		);
		$this->assertMatchesRegularExpression(
			'#outline\s*:\s*\d+px\s+solid\s+var\(--fc-focus-ring\)#',
			$css,
			'card link focus outline must use the focus-ring token'
		);
	}

	public function testReducedMotionHonored(): void
	{
		// WCAG 2.3.3 — animations / motion respect user preference.
		$css = $this->css();
		$this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
		$this->assertMatchesRegularExpression(
			'#prefers-reduced-motion:\s*reduce\)\s*\{[^}]*transition\s*:\s*none#s',
			$css,
			'reduced-motion block must disable transition'
		);
	}

	public function testDarkModeTokens(): void
	{
		// Tokens redefined under prefers-color-scheme: dark so focus ring
		// stays 3:1 against the inverted card background.
		$this->assertStringContainsString('@media (prefers-color-scheme: dark)', $this->css());
	}

	public function testCardLinkOverlayPattern(): void
	{
		// AT contract — one link per card, named by h2 title text. The
		// "::after with inset:0" overlay extends the click area without
		// nesting interactives or duplicating link labels.
		$this->assertMatchesRegularExpression(
			'#\.fcpt-title\s+a::after[^{]*\{[^}]*inset\s*:\s*0#s',
			$this->css(),
			'card-overlay click area must come from .fcpt-title a::after, not nested <a>'
		);
	}

	public function testLineClampKeepsTextInDom(): void
	{
		// WCAG 1.4.4 / 1.4.10 — line-clamp visually truncates but the
		// full text remains in the DOM for screen readers.
		$css = $this->css();
		$this->assertStringContainsString('-webkit-line-clamp', $css);
		$this->assertStringContainsString('-webkit-box-orient', $css);
		// Must NOT use display:none or visibility:hidden on the intro,
		// which would strip text from AT.
		$this->assertDoesNotMatchRegularExpression(
			'#\.fcpt-introtext[^{]*\{[^}]*(display\s*:\s*none|visibility\s*:\s*hidden)#s',
			$css,
			'fcpt-introtext must keep text in DOM for assistive tech'
		);
	}

	public function testCategoryViewRegistersStylesheetInBothBranches(): void
	{
		// View must register the stylesheet on BOTH Pro-Layout paths
		// (default + mcats) and only there — never globally for legacy
		// templates.
		$view = $this->root() . '/site/views/category/view.html.php';
		$src  = file_get_contents($view);
		$this->assertNotFalse($src);

		$occurrences = substr_count($src, "'fc-protemplate-frontend'");
		$this->assertGreaterThanOrEqual(2, $occurrences,
			'fc-protemplate-frontend asset must be registered in both Pro Layout branches');

		$this->assertStringContainsString(
			'protemplate_frontend.css',
			$src,
			'view must reference the new CSS filename'
		);
	}
}
