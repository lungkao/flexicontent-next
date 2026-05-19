<?php
/**
 * Unit tests for FlexicontentProTemplateFonts (Google Fonts auto-loader).
 *
 * The loader extracts the first Google-Fonts-catalog family from a CSS
 * font-family stack and emits the link tags. These tests cover the
 * pure-PHP helpers — splitStack, pickGoogleFamily, buildCssUrl,
 * buildScopeCss — without requiring a live Joomla document.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.8
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3)
	. '/admin/helpers/protemplate/Fonts.php';

class FontsLoaderTest extends TestCase
{
	public function testCatalogLoadsAndIsNonEmpty(): void
	{
		$catalog = \FlexicontentProTemplateFonts::catalog();
		$this->assertIsArray($catalog);
		$this->assertNotEmpty($catalog, 'Google Fonts catalog must load');
		$this->assertArrayHasKey('Inter', $catalog);
		$this->assertArrayHasKey('Sarabun', $catalog);
		$this->assertTrue((bool) $catalog['Sarabun']['thai']);
		$this->assertFalse((bool) $catalog['Inter']['thai']);
	}

	public function testSplitStackHandlesQuotedNames(): void
	{
		$result = \FlexicontentProTemplateFonts::splitStack(
			'Inter, "Helvetica Neue", system-ui, sans-serif'
		);
		$this->assertSame(
			['Inter', 'Helvetica Neue', 'system-ui', 'sans-serif'],
			$result
		);
	}

	public function testSplitStackHandlesSingleQuotedNames(): void
	{
		$result = \FlexicontentProTemplateFonts::splitStack(
			"'Playfair Display', Georgia, serif"
		);
		$this->assertSame(
			['Playfair Display', 'Georgia', 'serif'],
			$result
		);
	}

	public function testSplitStackEmptyString(): void
	{
		$this->assertSame([], \FlexicontentProTemplateFonts::splitStack(''));
	}

	public function testPickGoogleFamilyReturnsFirstMatch(): void
	{
		$picked = \FlexicontentProTemplateFonts::pickGoogleFamily(
			'Inter, system-ui, sans-serif'
		);
		$this->assertSame('Inter', $picked);
	}

	public function testPickGoogleFamilySkipsSystemFamiliesAndReturnsCatalogHit(): void
	{
		$picked = \FlexicontentProTemplateFonts::pickGoogleFamily(
			'system-ui, Inter, sans-serif'
		);
		$this->assertSame('Inter', $picked, 'must pick first catalog match even if not first in stack');
	}

	public function testPickGoogleFamilyReturnsNullWhenNoMatch(): void
	{
		$picked = \FlexicontentProTemplateFonts::pickGoogleFamily(
			'Helvetica, Arial, sans-serif'
		);
		$this->assertNull($picked);
	}

	public function testBuildCssUrlEmitsDisplaySwap(): void
	{
		$url = \FlexicontentProTemplateFonts::buildCssUrl(['Inter' => ['400','700']]);
		$this->assertStringContainsString('display=swap', $url);
		$this->assertStringStartsWith('https://fonts.googleapis.com/css2?', $url);
	}

	public function testBuildCssUrlEncodesSpacesAsPlus(): void
	{
		$url = \FlexicontentProTemplateFonts::buildCssUrl(['Playfair Display' => ['400','700']]);
		$this->assertStringContainsString('family=Playfair+Display:wght@400;700', $url);
	}

	public function testBuildCssUrlAppendsThaiSubsetWhenRequested(): void
	{
		$url = \FlexicontentProTemplateFonts::buildCssUrl(
			['Sarabun' => ['400','700']],
			true
		);
		$this->assertStringContainsString('subset=thai', $url);
	}

	public function testBuildCssUrlSortsAndDeduplicatesWeights(): void
	{
		$url = \FlexicontentProTemplateFonts::buildCssUrl(
			['Inter' => ['700','400','700']]
		);
		$this->assertStringContainsString('wght@400;700', $url);
	}

	public function testBuildCssUrlIgnoresUnknownFamilies(): void
	{
		$url = \FlexicontentProTemplateFonts::buildCssUrl(
			['Helvetica Made-Up' => ['400']]
		);
		$this->assertSame('', $url, 'Unknown family must produce empty URL, not garbage');
	}

	public function testBuildScopeCssEmitsBodyAndHeadingTokens(): void
	{
		$css = \FlexicontentProTemplateFonts::buildScopeCss(
			['body' => 'Inter', 'heading' => 'Playfair Display'],
			[
				'family'         => 'Inter, system-ui, sans-serif',
				'family_heading' => '"Playfair Display", Georgia, serif',
				'line_height'    => 1.6,
			]
		);
		$this->assertStringContainsString('--fc-font-body: Inter, system-ui, sans-serif', $css);
		$this->assertStringContainsString('--fc-font-heading:', $css);
		$this->assertStringContainsString('Playfair Display', $css);
		$this->assertStringContainsString('--fc-line-height: 1.6', $css);
		$this->assertStringContainsString('.fcpt-layout', $css);
	}

	public function testBuildScopeCssStripsTagInjection(): void
	{
		$css = \FlexicontentProTemplateFonts::buildScopeCss(
			['body' => null, 'heading' => null],
			['family' => 'Inter</style><script>alert(1)</script>']
		);
		$this->assertStringNotContainsString('<style', $css);
		$this->assertStringNotContainsString('<script', $css);
		$this->assertStringNotContainsString(';alert', $css, 'CSS-significant chars must be stripped');
	}

	public function testBuildScopeCssRejectsLineHeightOutOfRange(): void
	{
		$css = \FlexicontentProTemplateFonts::buildScopeCss(
			['body' => null, 'heading' => null],
			['family' => 'Inter, sans-serif', 'line_height' => 99]
		);
		$this->assertStringNotContainsString('--fc-line-height', $css);
	}

	public function testRegisterReturnsResolvedFamilies(): void
	{
		$doc = new \stdClass();
		$layout = [
			'settings' => [
				'theme_data' => [
					'typography' => [
						'family'         => 'Inter, system-ui, sans-serif',
						'family_heading' => '"Playfair Display", Georgia, serif',
					],
				],
			],
		];
		$result = \FlexicontentProTemplateFonts::register($doc, $layout);
		$this->assertSame('Inter', $result['body']);
		$this->assertSame('Playfair Display', $result['heading']);
	}

	public function testRegisterAcceptsObjectLayout(): void
	{
		$layout = json_decode(json_encode([
			'settings' => [
				'theme_data' => [
					'typography' => [
						'family'         => 'Sarabun, sans-serif',
						'family_heading' => 'Prompt, sans-serif',
					],
				],
			],
		]));
		$result = \FlexicontentProTemplateFonts::register(new \stdClass(), $layout);
		$this->assertSame('Sarabun', $result['body']);
		$this->assertSame('Prompt', $result['heading']);
	}

	public function testCatalogContainsCorePopularFamilies(): void
	{
		$catalog = \FlexicontentProTemplateFonts::catalog();
		$mustHave = [
			'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins',
			'Playfair Display', 'Lora', 'Cormorant Garamond',
			'JetBrains Mono', 'Quicksand',
			'Sarabun', 'Prompt', 'Kanit', 'Mitr', 'Noto Sans Thai',
		];
		foreach ($mustHave as $family) {
			$this->assertArrayHasKey($family, $catalog, "catalog must include $family");
		}
	}

	public function testThaiFamiliesFlaggedConsistently(): void
	{
		$catalog = \FlexicontentProTemplateFonts::catalog();
		foreach (['Sarabun','Prompt','Kanit','Mitr','Noto Sans Thai','IBM Plex Sans Thai'] as $thaiFamily) {
			$this->assertTrue((bool) $catalog[$thaiFamily]['thai'], "$thaiFamily must be flagged thai=true");
			$this->assertContains('thai', $catalog[$thaiFamily]['subsets'], "$thaiFamily subsets must include 'thai'");
		}
	}
}
