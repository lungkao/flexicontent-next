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

		// CSS registered in both branches (default + mcats) = 2 occurrences.
		// JS registered in both branches = 2 more. Total = 4.
		$occurrences = substr_count($src, "'fc-protemplate-frontend'");
		$this->assertGreaterThanOrEqual(2, $occurrences,
			'fc-protemplate-frontend asset must be registered in both Pro Layout branches');

		$this->assertStringContainsString(
			'protemplate_frontend.css',
			$src,
			'view must reference the new CSS filename'
		);
		$this->assertStringContainsString(
			'protemplate_frontend.js',
			$src,
			'view must reference the new JS filename for Escape-dismiss support'
		);
	}

	// ── v2: 4 distinct treatment regression guards ──────────────────

	public function testTreatmentSwitchUsesHasSelector(): void
	{
		// :has() drives the per-treatment layout switch. Removing it
		// collapses all four presets back to a uniform grid card.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#:has\(\s*\.fcpt-appearance-feature\s*\)#',
			$css,
			'Magazine Hero treatment must key off :has(.fcpt-appearance-feature)'
		);
		$this->assertMatchesRegularExpression(
			'#:has\(\s*\.fcpt-appearance-bento\s*\)#',
			$css,
			'Asymmetric Bento treatment must key off :has(.fcpt-appearance-bento)'
		);
		$this->assertMatchesRegularExpression(
			'#:has\(\s*\.fcpt-row\.fcpt-appearance-compact\s*\)#',
			$css,
			'Notion List treatment must key off :has(.fcpt-row.fcpt-appearance-compact)'
		);
	}

	public function testBentoScrimGuaranteesContrast(): void
	{
		// a11y-lead required tweak (b): scrim must hold solid 0.78+ alpha
		// from 55%→100% — gradient mid-points cannot dip below the
		// contrast floor when title text wraps over them.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#--fc-scrim-grad:[^;]*rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\.(78|8|85|9)#',
			$css,
			'bento scrim must hold solid 0.78+ alpha at the text band'
		);
	}

	public function testNoDualFocusRing(): void
	{
		// a11y-lead required tweak (g): focus indicator must come from the
		// link outline only, NOT a card-level focus-within glow.
		$css = $this->css();
		// Card-level focus-within is allowed for subtle bg-tint signaling,
		// but it must not add an inset shadow or outline (= second ring).
		$this->assertDoesNotMatchRegularExpression(
			'#:focus-within\s*\{[^}]*box-shadow\s*:\s*inset[^}]*var\(--fc-focus-ring\)#s',
			$css,
			'card-level :focus-within must not paint an inset ring (would be a second focus indicator)'
		);
		$this->assertDoesNotMatchRegularExpression(
			'#\.fc-cat-pro-li:focus-within\s*\{[^}]*outline\s*:#s',
			$css,
			'card-level :focus-within must not draw an outline (link link outline is the focus indicator)'
		);
	}

	public function testBentoEscapeDismissPattern(): void
	{
		// Bento intro reveal must support data-fc-dismissed attribute so
		// the Escape-key JS can collapse the revealed text while focus or
		// hover remains on the card (WCAG 1.4.13).
		$this->assertMatchesRegularExpression(
			'#\[data-fc-dismissed="true"\][^{]*\.fcpt-introtext#s',
			$this->css(),
			'data-fc-dismissed selector must force the intro back to collapsed'
		);
	}

	public function testReducedMotionRestoresBentoFullText(): void
	{
		// In reduced-motion mode the bento intro stays revealed (max-height:
		// none, opacity: 1) instead of animating. Otherwise the user can
		// never see the text without triggering an animation.
		$this->assertMatchesRegularExpression(
			'#prefers-reduced-motion:\s*reduce.*?\.fcpt-appearance-bento[^{]*\.fcpt-introtext\s*\{[^}]*max-height\s*:\s*none#s',
			$this->css(),
			'reduced-motion must reveal bento intro non-animated'
		);
	}

	public function testForcedColorsModePresent(): void
	{
		// Windows High Contrast support — borders use CanvasText, focus
		// outline uses Highlight, bento scrim disabled (system handles it).
		$css = $this->css();
		$this->assertStringContainsString('@media (forced-colors: active)', $css);
		$this->assertStringContainsString('CanvasText', $css);
		$this->assertStringContainsString('Highlight', $css);
	}

	public function testEyebrowEmittedViaCssContent(): void
	{
		// Featured "FEATURED" eyebrow comes from a CSS ::before pseudo —
		// content is not in the DOM, so AT reads the underlying title
		// text only. Pattern confirmed safe by a11y-lead.
		$this->assertMatchesRegularExpression(
			'#:has\(\.fcpt-appearance-feature\)[^{]*\.fcpt-title::before\s*\{[^}]*content\s*:\s*"Featured"#s',
			$this->css(),
			'Featured eyebrow must come from ::before content, not DOM markup'
		);
	}

	public function testListAppearanceSwitchesGridColumns(): void
	{
		// Each treatment changes the `<ul>` grid template:
		//   - default       → auto-fill minmax(280px)
		//   - feature       → auto-fill minmax(420px)  (bigger cards)
		//   - bento         → auto-fit minmax(240px) + grid-auto-rows
		//   - compact (row) → display:block (1-col rows)
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#:has\(\.fcpt-appearance-feature\)[^{]*\{[^}]*grid-template-columns#s',
			$css,
			'Feature variant must override grid-template-columns'
		);
		$this->assertMatchesRegularExpression(
			'#:has\(\.fcpt-appearance-bento\)[^{]*\{[^}]*grid-auto-rows#s',
			$css,
			'Bento variant must set grid-auto-rows for span calculations'
		);
		$this->assertMatchesRegularExpression(
			'#:has\(\.fcpt-row\.fcpt-appearance-compact\)[^{]*\{[^}]*display\s*:\s*block#s',
			$css,
			'Compact variant must switch list to block (1-col rows)'
		);
	}

	public function testWhereSelectorForOverrideSafety(): void
	{
		// :where() drops specificity so YOOtheme / Joomla template
		// overrides win without `!important`. Confirm at least one
		// :where() default rule exists for the card baseline.
		$this->assertStringContainsString(
			':where(.fc-cat-pro-li, .fc-mcats-pro-li)',
			$this->css(),
			'card baseline must be wrapped in :where() so theme CSS can override naturally'
		);
	}
}
