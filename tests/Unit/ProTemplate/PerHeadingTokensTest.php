<?php
/**
 * Regression guards for the per-heading-level (h1..h6) token surface
 * added in 6.1.0-beta.x.
 *
 * Renderer::buildThemeStyleAttribute() reads themeData['heading_levels']
 * keyed by 1..6 (each with size, weight, lineHeight, letterSpacing,
 * color) and emits:
 *
 *   --fc-h{N}-size            --fc-h{N}-weight
 *   --fc-h{N}-line-height     --fc-h{N}-letter-spacing
 *   --fc-h{N}-color
 *
 * The stylesheet must define consumer rules for each level so the
 * inline tokens actually paint. Unset levels fall back to the global
 * --fc-heading-* tokens (preserves prior behavior).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.x
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PerHeadingTokensTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once dirname(__DIR__, 3) . '/admin/helpers/protemplate/Renderer.php';
	}

	private function css(): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/site/assets/css/protemplate_frontend.css'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	private function invokeBuildThemeStyleAttribute(array $themeData): string
	{
		// buildThemeStyleAttribute is protected — reach through reflection
		// without instantiating Joomla MVC. The method is pure: themeData
		// in, style string out, no dependencies on $this state.
		$class = new ReflectionClass(\FlexicontentProTemplateRenderer::class);
		$instance = $class->newInstanceWithoutConstructor();
		$method = $class->getMethod('buildThemeStyleAttribute');
		return (string) $method->invoke($instance, $themeData);
	}

	/**
	 * @dataProvider headingLevels
	 */
	public function testEachLevelHasCssConsumer(int $lvl): void
	{
		$css = $this->css();
		foreach (['size', 'weight', 'line-height', 'letter-spacing', 'color'] as $prop) {
			$this->assertMatchesRegularExpression(
				"#\\bh{$lvl}\\s*\\{[^}]*{$prop}\\s*:\\s*var\\(--fc-h{$lvl}-{$prop}#s",
				$css,
				"h{$lvl} must consume --fc-h{$lvl}-{$prop} token"
			);
		}
	}

	public static function headingLevels(): array
	{
		return [
			'h1' => [1],
			'h2' => [2],
			'h3' => [3],
			'h4' => [4],
			'h5' => [5],
			'h6' => [6],
		];
	}

	public function testRendererEmitsPerLevelTokens(): void
	{
		$themeData = [
			'heading_levels' => [
				'1' => ['size' => '3rem',      'weight' => '800', 'color' => '#111111'],
				'2' => ['size' => '2.25rem',   'weight' => '700'],
				'3' => ['size' => '1.75rem',   'lineHeight' => '1.3'],
				'4' => ['size' => '1.375rem'],
				'5' => ['size' => '1.125rem', 'letterSpacing' => '0.02em'],
				'6' => ['color' => '#475569'],
			],
		];

		$style = $this->invokeBuildThemeStyleAttribute($themeData);

		// h1 — size + weight + color all emitted.
		$this->assertStringContainsString('--fc-h1-size: 3rem',    $style);
		$this->assertStringContainsString('--fc-h1-weight: 800',   $style);
		$this->assertStringContainsString('--fc-h1-color: #111111', $style);

		// h2 — partial fields only.
		$this->assertStringContainsString('--fc-h2-size: 2.25rem', $style);
		$this->assertStringContainsString('--fc-h2-weight: 700',   $style);
		$this->assertStringNotContainsString('--fc-h2-color',      $style);

		// h3 — lineHeight maps to --fc-h3-line-height.
		$this->assertStringContainsString('--fc-h3-line-height: 1.3', $style);

		// h5 — letterSpacing maps to --fc-h5-letter-spacing.
		$this->assertStringContainsString('--fc-h5-letter-spacing: 0.02em', $style);

		// h6 — color only.
		$this->assertStringContainsString('--fc-h6-color: #475569', $style);
		$this->assertStringNotContainsString('--fc-h6-size',        $style);
	}

	public function testEmptyHeadingLevelsEmitsNoTokens(): void
	{
		$style = $this->invokeBuildThemeStyleAttribute([]);
		for ($i = 1; $i <= 6; $i++) {
			$this->assertStringNotContainsString("--fc-h{$i}-",
				$style,
				"empty themeData must not emit any per-level heading tokens"
			);
		}
	}

	public function testHeadingLevelSanitization(): void
	{
		// sanitizeCssToken() strips CSS terminators (;, {, }, ", \n, \r)
		// and tag chars. Remaining text stays inside the property *value*
		// where it is harmless — the security boundary is preventing
		// escape into a sibling declaration, not scrubbing keywords.
		$style = $this->invokeBuildThemeStyleAttribute([
			'heading_levels' => [
				'1' => ['size' => '3rem; background: url(evil)'],
				'2' => ['color' => '<script>alert(1)</script>'],
			],
		]);
		// `;` is the actual injection vector — must be gone so no new
		// CSS declaration can be smuggled in.
		$this->assertStringNotContainsString(
			'--fc-h1-size: 3rem;',
			$style,
			'`;` terminator inside heading_levels value must be stripped to prevent declaration injection'
		);
		// Tag-injection chars must be stripped from the inline-style
		// attribute (the value still appears HTML-escaped but contains
		// no live `<` chars).
		$this->assertStringNotContainsString('<script', $style,
			'`<script` token must not survive sanitization in the inline style attribute'
		);
		// Style attribute must remain well-formed: one set of quotes
		// around the value, no embedded quotes.
		$this->assertMatchesRegularExpression(
			'#^ style="[^"]*"$#',
			$style,
			'inline style attribute must remain a single quoted value'
		);
	}
}
