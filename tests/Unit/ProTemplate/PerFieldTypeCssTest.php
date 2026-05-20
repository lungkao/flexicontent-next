<?php
/**
 * Regression guards for the per-field-type CSS hooks shipped in
 * 6.1.0-beta.x (data-fcpt-field-type attribute → stylesheet).
 *
 * Renderer.renderField() emits:
 *   <div class="fcpt-field …" data-fcpt-field-type="<type>">
 *     <div class="fcpt-field__label" id="…-label">
 *     <div class="fcpt-field__value" aria-labelledby="…-label">
 *
 * The stylesheet must provide visual treatment per type group AND
 * encode the a11y constraints cleared by accessibility-lead:
 *   - <a> sits above the card-wide overlay link (z-index)
 *   - :focus-visible ring using --fc-focus-ring
 *   - Target size ≥24×24 CSS px via min-height + padding
 *   - Non-color signal for booleanlist (icon ::before)
 *   - Forced-colors + reduced-motion overrides
 *
 * A11y-cleared (accessibility-lead, 2026-05-20).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.x
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class PerFieldTypeCssTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	private function css(): string
	{
		$src = file_get_contents($this->root() . '/site/assets/css/protemplate_frontend.css');
		$this->assertNotFalse($src, 'protemplate_frontend.css missing or unreadable');
		return $src;
	}

	public function testBaseFieldBlockStyled(): void
	{
		$css = $this->css();
		// .fcpt-field wrapper + label + value must each have at least
		// one rule. Without them the Renderer output renders raw.
		$this->assertMatchesRegularExpression(
			'#:where\(\.fc-cat-pro-li, \.fc-mcats-pro-li\)\s+\.fcpt-field\s*\{#',
			$css,
			'fcpt-field wrapper must be styled inside card context'
		);
		$this->assertMatchesRegularExpression(
			'#:where\(\.fc-cat-pro-li, \.fc-mcats-pro-li\)\s+\.fcpt-field__label\s*\{#',
			$css,
			'fcpt-field__label must be styled'
		);
		$this->assertMatchesRegularExpression(
			'#:where\(\.fc-cat-pro-li, \.fc-mcats-pro-li\)\s+\.fcpt-field__value\s*\{#',
			$css,
			'fcpt-field__value must be styled'
		);
	}

	/**
	 * Each core field type must own at least one rule keyed off the
	 * data-fcpt-field-type attribute.
	 *
	 * @dataProvider coreFieldTypes
	 */
	public function testCoreFieldTypeHasHook(string $type): void
	{
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-field-type="' . preg_quote($type, '#') . '"\]#',
			$css,
			"field type '$type' must have a [data-fcpt-field-type] CSS hook"
		);
	}

	public static function coreFieldTypes(): array
	{
		// Six groups × representative types.
		return [
			'image'           => ['image'],
			'mediafile'       => ['mediafile'],
			'sharedmedia'     => ['sharedmedia'],
			'file'            => ['file'],
			'textarea'        => ['textarea'],
			'date'            => ['date'],
			'email'           => ['email'],
			'weblink'         => ['weblink'],
			'phonenumbers'    => ['phonenumbers'],
			'addressint'      => ['addressint'],
			'color'           => ['color'],
			'relation'        => ['relation'],
			'relation_reverse' => ['relation_reverse'],
			'termlist'        => ['termlist'],
			'linkslist'       => ['linkslist'],
			'checkbox'        => ['checkbox'],
			'radio'           => ['radio'],
			'select'          => ['select'],
			'selectmultiple'  => ['selectmultiple'],
			'textselect'      => ['textselect'],
			'checkboximage'   => ['checkboximage'],
			'radioimage'      => ['radioimage'],
			'fieldgroup'      => ['fieldgroup'],
			'subform'         => ['subform'],
			'booleanlist'     => ['booleanlist'],
		];
	}

	public function testAnchorsOverlayCoexistence(): void
	{
		// MUST per a11y-lead: every <a> inside __value sits above the
		// card-wide .fcpt-title a::after overlay → position:relative
		// + z-index ≥1, otherwise the chip is keyboard-focusable but
		// mouse-dead.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\.fcpt-field__value\s+a\s*\{[^}]*position\s*:\s*relative[^}]*z-index\s*:\s*1#s',
			$css,
			'fcpt-field__value anchors must carry position:relative + z-index:1 to clear the card overlay'
		);
	}

	public function testAnchorFocusRingUsesToken(): void
	{
		// WCAG 2.4.7 / 2.4.13 — visible focus ring on every link in
		// field values, painted via --fc-focus-ring.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\.fcpt-field__value\s+a:focus-visible\s*\{[^}]*outline\s*:\s*\d+px\s+solid\s+var\(--fc-focus-ring#s',
			$css,
			'anchors in field values must render :focus-visible ring via --fc-focus-ring token'
		);
	}

	public function testListsResetBullets(): void
	{
		// Pill list groups depend on list-style:none + flex wrap.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\.fcpt-field__value\s+ul[^{]*\{[^}]*list-style\s*:\s*none[^}]*flex-wrap\s*:\s*wrap#s',
			$css,
			'field-value <ul> must reset bullets and flex-wrap for pill layout'
		);
	}

	public function testPillTargetSize(): void
	{
		// WCAG 2.5.8 AA — clickable pill ≥24×24 CSS px. Asserted via
		// the file/mediafile chip rule (min-height: 2.25rem ≈ 36 px).
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-field-type="file"\][^{]*\.fcpt-field__value\s+a\s*\{[^}]*min-height\s*:\s*2(\.\d+)?rem#s',
			$css,
			'file chips must declare min-height ≥2rem (≥32 px) for target size compliance'
		);
	}

	public function testBooleanlistHasNonColorSignal(): void
	{
		// WCAG 1.4.1 — booleanlist must reinforce state with a
		// non-color cue: icon glyph via ::before, weight bump.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-field-type="booleanlist"\][^{]*\.fcpt-field__value::before\s*\{[^}]*content\s*:#s',
			$css,
			'booleanlist must paint a ::before icon glyph (non-color signal per 1.4.1)'
		);
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-field-type="booleanlist"\][^{]*\.fcpt-field__value\s*\{[^}]*font-weight\s*:\s*[5-9]00#s',
			$css,
			'booleanlist value must bump font-weight as a secondary non-color cue'
		);
	}

	public function testColorSwatchPreservesTextLabel(): void
	{
		// WCAG 1.4.1 — color swatch alone is hue-only. CSS must NOT
		// hide the adjacent text label.
		$css = $this->css();
		$this->assertDoesNotMatchRegularExpression(
			'#\[data-fcpt-field-type="color"\][^{]*\.fcpt-field__value\s*\{[^}]*(display\s*:\s*none|visibility\s*:\s*hidden)#s',
			$css,
			'color field must not hide its text label (1.4.1 compliance)'
		);
	}

	public function testForcedColorsCoversFieldValue(): void
	{
		// Forced-colors fallback for chips inside field values.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#@media\s*\(forced-colors:\s*active\)\s*\{[^}]*\.fcpt-field__value\s+a\s*\{[^}]*CanvasText#s',
			$css,
			'forced-colors must set CanvasText border on field-value anchors'
		);
		$this->assertMatchesRegularExpression(
			'#@media\s*\(forced-colors:\s*active\)[\s\S]*?\.fcpt-field__value\s+ul\s+li\s*\{[^}]*CanvasText#s',
			$css,
			'forced-colors must set CanvasText border on pill list items'
		);
	}

	public function testReducedMotionCoversFieldChips(): void
	{
		// Per a11y-lead SHOULD — extend reduced-motion to disable
		// pill transitions, not only card-level animations.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#prefers-reduced-motion:\s*reduce\)[\s\S]*?\.fcpt-field__value\s+a[\s\S]*?transition\s*:\s*none#s',
			$css,
			'reduced-motion must disable pill transitions on field-value anchors'
		);
	}

	public function testCompositeFieldsUseLogicalPropertiesForRtl(): void
	{
		// Nice-to-have per a11y-lead — fieldgroup/subform indent uses
		// logical properties (padding-inline-start + border-inline-start)
		// so RTL Just Works.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-field-type="(fieldgroup|subform)"\][^{]*\{[^}]*padding-inline-start#s',
			$css,
			'composite field indent must use padding-inline-start (RTL-safe)'
		);
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-field-type="(fieldgroup|subform)"\][^{]*\{[^}]*border-inline-start#s',
			$css,
			'composite field accent stripe must use border-inline-start (RTL-safe)'
		);
	}

	public function testNestedLabelHierarchyPreserved(): void
	{
		// SHOULD per a11y-lead — nested .fcpt-field__label inside
		// fieldgroup/subform must render visually smaller so the
		// aria-labelledby hierarchy is perceivable.
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-field-type="(fieldgroup|subform)"\]\s+\.fcpt-field\s+\.fcpt-field__label\s*\{[^}]*font-size\s*:\s*0\.6\d+rem#s',
			$css,
			'nested labels inside composite fields must be visually smaller'
		);
	}
}
