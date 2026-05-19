<?php
/**
 * Regression guards for the protemplate_font form field shipped in
 * 6.1.0-beta.8 (v3).
 *
 * The picker is an ARIA 1.2 combobox bound to the curated Google Fonts
 * catalog. These tests pin the contract by source-text inspection:
 * required ARIA attributes, JS keyboard handlers, CSS focus-ring,
 * forced-colors fallback, and the Joomla field class signature.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.8
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class FontPickerFieldTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	private function php(): string
	{
		return file_get_contents($this->root() . '/admin/models/fields/protemplate_font.php');
	}

	private function js(): string
	{
		return file_get_contents($this->root() . '/admin/assets/js/protemplate_font_picker.js');
	}

	private function css(): string
	{
		return file_get_contents($this->root() . '/admin/assets/css/protemplate_font_picker.css');
	}

	// ── PHP form-field class ──────────────────────────────────────

	public function testFormFieldClassExists(): void
	{
		$this->assertFileExists($this->root() . '/admin/models/fields/protemplate_font.php');
	}

	public function testFormFieldExtendsJoomlaFormField(): void
	{
		$src = $this->php();
		$this->assertStringContainsString('class JFormFieldProtemplate_font extends FormField', $src,
			'Field class name must match Joomla form-field convention');
		$this->assertStringContainsString("protected \$type = 'Protemplate_font'", $src);
	}

	public function testFormFieldRendersAriaCombobox(): void
	{
		$src = $this->php();
		$this->assertStringContainsString('role="combobox"', $src);
		$this->assertStringContainsString('aria-expanded="false"', $src);
		$this->assertStringContainsString('aria-controls=', $src);
		$this->assertStringContainsString('aria-autocomplete="list"', $src);
	}

	public function testFormFieldRendersListboxWithRole(): void
	{
		$src = $this->php();
		$this->assertStringContainsString('role="listbox"', $src);
		$this->assertStringContainsString('hidden', $src);
	}

	public function testFormFieldRendersLiveRegion(): void
	{
		$src = $this->php();
		$this->assertStringContainsString('aria-live="polite"', $src);
		$this->assertStringContainsString('role="status"', $src);
	}

	public function testFormFieldStoresFullStackInHiddenInput(): void
	{
		$src = $this->php();
		$this->assertStringContainsString('type="hidden"', $src);
		$this->assertStringContainsString('data-fcpt-stack-value', $src);
	}

	public function testFormFieldFiltersCatalogByCategoryAndThai(): void
	{
		$src = $this->php();
		$this->assertStringContainsString("\$this->element['category']", $src);
		$this->assertStringContainsString("\$this->element['thai']", $src);
	}

	// ── JS combobox behaviour ─────────────────────────────────────

	public function testJsHandlesArrowKeysAndEnterAndEsc(): void
	{
		$js = $this->js();
		$this->assertStringContainsString('ArrowDown', $js);
		$this->assertStringContainsString('ArrowUp',   $js);
		$this->assertStringContainsString('Home',      $js);
		$this->assertStringContainsString('End',       $js);
		$this->assertStringContainsString('Enter',     $js);
		$this->assertStringContainsString('Escape',    $js);
	}

	public function testJsMovesActiveDescendantNotDomFocus(): void
	{
		$js = $this->js();
		$this->assertStringContainsString('aria-activedescendant', $js,
			'must move ARIA active-descendant, not shift DOM focus');
	}

	public function testJsRendersAriaSetsizeAndPosinset(): void
	{
		$js = $this->js();
		$this->assertStringContainsString('aria-setsize', $js);
		$this->assertStringContainsString('aria-posinset', $js);
		$this->assertStringContainsString('aria-selected', $js);
	}

	public function testJsAnnouncesResultCount(): void
	{
		$js = $this->js();
		$this->assertStringContainsString('data-fcpt-live', $js);
		$this->assertMatchesRegularExpression(
			'#labels\.results.*%d#s',
			$js,
			'JS must announce filter result count via the live region'
		);
	}

	public function testJsBuildStackPreservesFallback(): void
	{
		$js = $this->js();
		$this->assertStringContainsString('FALLBACK_BY_CATEGORY', $js);
		$this->assertStringContainsString('system-ui, sans-serif', $js);
	}

	public function testJsIdempotentBinding(): void
	{
		$js = $this->js();
		$this->assertStringContainsString('fcptBound', $js);
		$this->assertMatchesRegularExpression(
			'#dataset\.fcptBound\s*===\s*"1"#',
			$js
		);
	}

	public function testJsWatchesForLateInjection(): void
	{
		$this->assertStringContainsString('MutationObserver', $this->js());
	}

	// ── CSS contract ──────────────────────────────────────────────

	public function testCssDeclaresFocusVisibleRing(): void
	{
		$css = $this->css();
		$this->assertStringContainsString(':focus-visible', $css);
		$this->assertMatchesRegularExpression(
			'#outline:\s*3px solid var\(--fcpt-fp-focus-ring\)#',
			$css
		);
	}

	public function testCssHasForcedColorsFallback(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('@media (forced-colors: active)', $css);
		$this->assertStringContainsString('CanvasText', $css);
		$this->assertStringContainsString('Highlight', $css);
		$this->assertStringContainsString('HighlightText', $css);
	}

	public function testCssReducedMotion(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
	}

	public function testCssScopedUnderPickerRoot(): void
	{
		$css = $this->css();
		$this->assertGreaterThan(
			15,
			substr_count($css, '.fcpt-font-picker'),
			'Picker CSS must be scoped under .fcpt-font-picker'
		);
	}

	public function testCssDarkSchemeTokensRedefined(): void
	{
		$this->assertStringContainsString('@media (prefers-color-scheme: dark)', $this->css());
	}
}
