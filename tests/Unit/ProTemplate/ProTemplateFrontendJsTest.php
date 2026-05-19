<?php
/**
 * Regression guards for the Pro Templates frontend JS shipped in
 * 6.1.0-beta.8+ to satisfy WCAG 1.4.13 (Content on Hover or Focus).
 *
 * The bento overlay treatment reveals introtext on hover/focus. The
 * stylesheet alone provides "persistent" and "hoverable"; the JS layer
 * adds "dismissible" — Escape key collapses the reveal while focus or
 * pointer can stay on the card.
 *
 * These tests pin the contract by source-text inspection (no JSDOM in
 * this suite): selectors, attribute name, registration in category view.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.8
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class ProTemplateFrontendJsTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	private function jsPath(): string
	{
		return $this->root() . '/site/assets/js/protemplate_frontend.js';
	}

	private function js(): string
	{
		$src = file_get_contents($this->jsPath());
		$this->assertNotFalse($src, 'protemplate_frontend.js missing or unreadable');
		return $src;
	}

	public function testScriptExists(): void
	{
		$this->assertFileExists($this->jsPath(),
			'Pro Layout frontend JS must ship in site/assets/js/');
	}

	public function testListensForEscapeKey(): void
	{
		// WCAG 1.4.13 dismissibility — must handle Escape via keydown.
		$js = $this->js();
		$this->assertStringContainsString('keydown', $js);
		$this->assertMatchesRegularExpression(
			'#ev\.key\s*!==\s*"Escape"#',
			$js,
			'must guard on ev.key === "Escape"'
		);
	}

	public function testFlipsDismissedDataAttribute(): void
	{
		// CSS keys off [data-fc-dismissed="true"] to force the intro back
		// to collapsed. Must use exactly that attribute name.
		$js = $this->js();
		$this->assertStringContainsString('data-fc-dismissed', $js);
		$this->assertMatchesRegularExpression(
			'#setAttribute\(\s*DISMISSED_ATTR\s*,\s*"true"#',
			$js,
			'dismiss() must set attribute to literal "true"'
		);
	}

	public function testNoOpsWhenNoBentoCardPresent(): void
	{
		// Inert payload for non-bento presets. Must short-circuit wireList()
		// when no .fcpt-appearance-bento descendant exists.
		$this->assertMatchesRegularExpression(
			'#if\s*\(\s*!list\.querySelector\(\s*BENTO_SELECTOR\s*\)\s*\)\s*return;#',
			$this->js(),
			'wireList must short-circuit when no bento card is present'
		);
	}

	public function testIdempotentBinding(): void
	{
		// Re-running wireList on the same node (e.g. AJAX reload) must
		// not double-attach handlers. Idempotency via dataset marker.
		$js = $this->js();
		$this->assertStringContainsString('fcProtemplateBound', $js);
		$this->assertMatchesRegularExpression(
			'#if\s*\(\s*list\.dataset\.fcProtemplateBound\s*===\s*"1"\s*\)\s*return#',
			$js,
			'binding must be idempotent — second wireList() call must return early'
		);
	}

	public function testWatchesForLateLists(): void
	{
		// AJAX category reload / infinite-scroll: lists may appear after
		// initial DOMContentLoaded. MutationObserver re-wires them.
		$js = $this->js();
		$this->assertStringContainsString('MutationObserver', $js);
		$this->assertStringContainsString('observer.observe', $js);
	}

	public function testDegradesOnLegacyBrowsers(): void
	{
		// Feature checks so the script no-ops cleanly on missing API.
		$js = $this->js();
		$this->assertStringContainsString('if (typeof document === "undefined"', $js);
		$this->assertStringContainsString('typeof MutationObserver !== "undefined"', $js);
	}

	public function testClearsDismissOnFullExit(): void
	{
		// When pointer / focus fully leaves the card the dismissed flag
		// must clear so the next visit re-reveals naturally. Must check
		// relatedTarget to distinguish "moved within card" from "left card".
		$js = $this->js();
		$this->assertStringContainsString('mouseout', $js);
		$this->assertStringContainsString('focusout', $js);
		$this->assertMatchesRegularExpression(
			'#if\s*\(\s*to\s*&&\s*card\.contains\(to\)\s*\)\s*return#s',
			$js,
			'must skip clearDismiss when pointer/focus stays inside the card'
		);
	}

	public function testCategoryViewRegistersScript(): void
	{
		$view = $this->root() . '/site/views/category/view.html.php';
		$src  = file_get_contents($view);
		$this->assertNotFalse($src);

		$this->assertStringContainsString(
			'protemplate_frontend.js',
			$src,
			'view must register the new JS asset'
		);

		// JS registered in BOTH Pro Layout branches (default + mcats).
		$jsRegistrations = substr_count($src, 'protemplate_frontend.js');
		$this->assertGreaterThanOrEqual(2, $jsRegistrations,
			'JS must be registered in both Pro Layout branches');

		// Loaded with defer so it does not block first paint.
		$this->assertMatchesRegularExpression(
			"#registerAndUseScript\(\s*'fc-protemplate-frontend'.*?'defer'\s*=>\s*true#s",
			$src,
			'JS asset must be registered with defer => true to avoid blocking paint'
		);
	}
}
