<?php
/**
 * Regression guards for the gradient + animation token consumers in
 * site/assets/css/protemplate_frontend.css.
 *
 * Renderer::themeStyleAttr() (admin/helpers/protemplate/Renderer.php
 * lines 813-847) emits these inline CSS tokens when a Custom Theme
 * picks appearance.gradientPreset / .animation / .surfaceStyle:
 *
 *   --fc-bg-image          linear-gradient(...)
 *   --fc-bg-animation      fcBgDrift <8|16>s ease-in-out infinite alternate
 *   --fc-bg-size           220% 220%
 *   --fc-surface-backdrop  blur(20px) saturate(140%)
 *
 * Without consumers in the stylesheet the tokens are emitted but no
 * element consumes them and the visual effect never renders. These
 * tests pin the consumers in place.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.x
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class GradientAnimationCssTest extends TestCase
{
	private function css(): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/site/assets/css/protemplate_frontend.css'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	public function testListConsumesBgImageToken(): void
	{
		$this->assertMatchesRegularExpression(
			'#\.fc-cat-pro-list,\s*\.fc-mcats-pro-list\s*\{[^}]*background-image\s*:\s*var\(--fc-bg-image#s',
			$this->css(),
			'category lists must read background-image from --fc-bg-image token'
		);
	}

	public function testListConsumesBgAnimationToken(): void
	{
		$this->assertMatchesRegularExpression(
			'#\.fc-cat-pro-list,\s*\.fc-mcats-pro-list\s*\{[^}]*animation\s*:\s*var\(--fc-bg-animation#s',
			$this->css(),
			'category lists must read animation from --fc-bg-animation token'
		);
	}

	public function testListConsumesBgSizeToken(): void
	{
		$this->assertMatchesRegularExpression(
			'#\.fc-cat-pro-list,\s*\.fc-mcats-pro-list\s*\{[^}]*background-size\s*:\s*var\(--fc-bg-size#s',
			$this->css(),
			'category lists must read background-size from --fc-bg-size token'
		);
	}

	public function testCardConsumesBackdropToken(): void
	{
		$css = $this->css();
		$this->assertMatchesRegularExpression(
			'#:where\(\.fc-cat-pro-li, \.fc-mcats-pro-li\)\s*\{[^}]*-webkit-backdrop-filter\s*:\s*var\(--fc-surface-backdrop#s',
			$css,
			'cards must read -webkit-backdrop-filter from --fc-surface-backdrop token'
		);
		$this->assertMatchesRegularExpression(
			'#:where\(\.fc-cat-pro-li, \.fc-mcats-pro-li\)\s*\{[^}]*\sbackdrop-filter\s*:\s*var\(--fc-surface-backdrop#s',
			$css,
			'cards must read standard backdrop-filter from --fc-surface-backdrop token'
		);
	}

	public function testFcBgDriftKeyframesDefined(): void
	{
		$this->assertMatchesRegularExpression(
			'#@keyframes\s+fcBgDrift\s*\{[^}]*background-position#s',
			$this->css(),
			'@keyframes fcBgDrift must exist and animate background-position'
		);
	}

	public function testReducedMotionStopsBgAnimation(): void
	{
		$this->assertMatchesRegularExpression(
			'#prefers-reduced-motion:\s*reduce[\s\S]*?\.fc-cat-pro-list[\s\S]*?animation\s*:\s*none#s',
			$this->css(),
			'reduced-motion must force list background animation to none'
		);
	}

	public function testForcedColorsDropsBgImage(): void
	{
		$this->assertMatchesRegularExpression(
			'#forced-colors:\s*active[\s\S]*?\.fc-cat-pro-list[\s\S]*?background-image\s*:\s*none#s',
			$this->css(),
			'forced-colors must remove decorative background-image from category lists'
		);
	}
}
