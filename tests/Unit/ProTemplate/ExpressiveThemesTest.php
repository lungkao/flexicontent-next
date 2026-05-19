<?php
/**
 * Regression guards for the 6 expressive themes + 4 animations shipped
 * in 6.1.0-beta.8 (v3).
 *
 * Each theme overrides the token surface via a `[data-fcpt-theme="X"]`
 * selector. Each animation respects prefers-reduced-motion AND has a
 * forced-colors-mode fallback.
 *
 * A11y-cleared by accessibility-lead 2026-05-19 (Round 2).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.8
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class ExpressiveThemesTest extends TestCase
{
	private function css(): string
	{
		$path = dirname(__DIR__, 3) . '/site/assets/css/protemplate_frontend.css';
		$src  = file_get_contents($path);
		$this->assertNotFalse($src);
		return $src;
	}

	public function testThemesPresent(): void
	{
		$css = $this->css();
		foreach (['aurora', 'sunset', 'ocean-glass', 'cyber-neon', 'pastel-dream', 'monochrome-plus'] as $key) {
			$this->assertStringContainsString(
				'[data-fcpt-theme="' . $key . '"]',
				$css,
				'theme ' . $key . ' must declare a [data-fcpt-theme] selector'
			);
		}
	}

	public function testEveryThemeShipsAccentSolidFallback(): void
	{
		$css = $this->css();
		$this->assertGreaterThanOrEqual(
			6,
			substr_count($css, '--fc-accent-solid:'),
			'each theme must define --fc-accent-solid for gradient-text fallback'
		);
	}

	public function testCyberNeonDeclaresColorScheme(): void
	{
		$this->assertMatchesRegularExpression(
			'#\[data-fcpt-theme="cyber-neon"\][^{]*\{[^}]*color-scheme:\s*dark#s',
			$this->css(),
			'Cyber Neon must declare color-scheme: dark for native form/scrollbar rendering'
		);
	}

	public function testOceanGlassFallsBackBeforeBackdropFilter(): void
	{
		$this->assertStringContainsString('@supports (backdrop-filter: blur(1px))', $this->css());
	}

	public function testMonochromeHueAnimationGatedOnReducedMotion(): void
	{
		$css = $this->css();
		// The animation declaration and the no-preference gate must
		// co-exist; the gate must precede the animation rule textually.
		$gatePos = strpos($css, 'prefers-reduced-motion: no-preference');
		$animPos = strpos($css, 'animation: fcMonochromeHue');
		$this->assertNotFalse($gatePos, 'prefers-reduced-motion: no-preference gate must exist');
		$this->assertNotFalse($animPos, 'fcMonochromeHue animation must exist');
		$this->assertLessThan(
			$animPos,
			$gatePos,
			'no-preference gate must appear before the monochrome hue animation'
		);
	}

	public function testGradientTextWrappedInClipSupports(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('-webkit-background-clip: text', $css);
		$this->assertStringContainsString('background-clip: text', $css);
		$this->assertMatchesRegularExpression(
			'#@supports \(\(\-webkit-background-clip: text\) or \(background-clip: text\)\)#',
			$css,
			'gradient-text must be wrapped in @supports for background-clip'
		);
	}

	public function testForcedColorsResetsGradientText(): void
	{
		$this->assertMatchesRegularExpression(
			'#forced-colors:\s*active\)[^{]*\{[^{]*\[data-fcpt-theme\][^}]*\.fcpt-title[^{]*\{[^}]*background:\s*none[^}]*color:\s*CanvasText#s',
			$this->css(),
			'forced-colors mode must reset gradient text to CanvasText'
		);
	}

	public function testEntryAnimationOptInClass(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('fc-pro-entry-ready', $css);
		$this->assertMatchesRegularExpression(
			'#prefers-reduced-motion:\s*no-preference\)[^{]*\{[^{]*fc-pro-entry-ready#s',
			$css,
			'entry animation must be inside a prefers-reduced-motion: no-preference block'
		);
	}

	public function testGradientSweepIterationCapped(): void
	{
		$this->assertMatchesRegularExpression(
			'#animation:\s*fcGradSweep[^;]+\s+3\s+alternate#',
			$this->css(),
			'gradient sweep must use 3 iterations, not infinite (WCAG 2.2.2)'
		);
	}

	public function testBentoMeshHasReducedMotionGuard(): void
	{
		$this->assertMatchesRegularExpression(
			'#prefers-reduced-motion:\s*no-preference\)[^{]*\{[^{]*\.fcpt-appearance-bento[^{]*\.fcpt-image-intro::before#s',
			$this->css(),
			'bento mesh animation must be inside prefers-reduced-motion no-preference'
		);
	}

	public function testForcedColorsThemeFallback(): void
	{
		$this->assertMatchesRegularExpression(
			'#forced-colors:\s*active\)[^{]*\{[^{]*\[data-fcpt-theme\][^}]*--fc-accent:\s*LinkText[^}]*--fc-focus-ring:\s*Highlight#s',
			$this->css(),
			'forced-colors mode must override theme tokens to system colors'
		);
	}

	public function testPrintStylesheet(): void
	{
		$css = $this->css();
		// @media print block exists, and inside it cards switch from
		// grid to block for sane page-break.
		$printPos = strpos($css, '@media print');
		$this->assertNotFalse($printPos);
		$tail = substr($css, $printPos);
		$this->assertMatchesRegularExpression(
			'#\.fc-cat-pro-list[^{}]*\{[^}]*display:\s*block#s',
			$tail,
			'print block must override grid to block for page-break'
		);
		$this->assertMatchesRegularExpression(
			'#break-inside:\s*avoid#',
			$tail,
			'print block must use break-inside: avoid on cards'
		);
	}

	public function testFontTokensDeclared(): void
	{
		$css = $this->css();
		$this->assertStringContainsString('--fc-font-body:', $css);
		$this->assertStringContainsString('--fc-font-heading:', $css);
		$this->assertStringContainsString('font-family: var(--fc-font-body)', $css);
		$this->assertStringContainsString('font-family: var(--fc-font-heading)', $css);
	}
}
