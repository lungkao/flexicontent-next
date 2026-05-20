<?php
/**
 * Regression guards for the Pro Theme editor's token-driven live
 * preview pane.
 *
 * The admin theme editor (admin/views/protheme/tmpl/default.php)
 * renders a mock category card that consumes the same CSS custom
 * properties Renderer::buildThemeStyleAttribute() emits in
 * production. The view class (admin/views/protheme/view.html.php)
 * must enqueue the frontend stylesheet so the production CSS rules
 * actually apply to the mock card.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.12
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class LivePreviewTest extends TestCase
{
	private function template(): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/admin/views/protheme/tmpl/default.php'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	private function view(): string
	{
		$src = file_get_contents(
			dirname(__DIR__, 3) . '/admin/views/protheme/view.html.php'
		);
		$this->assertNotFalse($src);
		return $src;
	}

	public function testTokenStyleStringMethodPresent(): void
	{
		$this->assertMatchesRegularExpression(
			'#tokenStyleString\s*\(\)\s*\{#',
			$this->template(),
			'Alpine component must declare tokenStyleString() method'
		);
	}

	/**
	 * @dataProvider coreTokens
	 */
	public function testTokenStyleStringEmitsCoreToken(string $token): void
	{
		$this->assertStringContainsString(
			"'" . $token . "'",
			$this->template(),
			"tokenStyleString must emit the '$token' custom property"
		);
	}

	public static function coreTokens(): array
	{
		return [
			'--fc-accent'             => ['--fc-accent'],
			'--fc-accent-solid'       => ['--fc-accent-solid'],
			'--fc-card-bg'            => ['--fc-card-bg'],
			'--fc-card-bg-elev'       => ['--fc-card-bg-elev'],
			'--fc-card-border'        => ['--fc-card-border'],
			'--fc-text'               => ['--fc-text'],
			'--fc-text-muted'         => ['--fc-text-muted'],
			'--fc-focus-ring'         => ['--fc-focus-ring'],
			'--fc-link-color'         => ['--fc-link-color'],
			'--fc-heading-color'      => ['--fc-heading-color'],
			'--fc-font-body'          => ['--fc-font-body'],
			'--fc-font-heading'       => ['--fc-font-heading'],
		];
	}

	public function testTokenStyleStringEmitsHeadingLevels(): void
	{
		$src = $this->template();
		$this->assertStringContainsString("'--fc-h' + n + '-size'", $src,
			'tokenStyleString must emit --fc-h{N}-size in the heading-levels loop'
		);
		$this->assertMatchesRegularExpression(
			'#for\s*\(let\s+n\s*=\s*1\s*;\s*n\s*<=\s*6\s*;#',
			$src,
			'tokenStyleString must iterate heading levels 1..6'
		);
	}

	public function testTokenStyleStringEmitsGradientAnimationTokens(): void
	{
		$src = $this->template();
		$this->assertStringContainsString("'--fc-bg-image'",     $src);
		$this->assertStringContainsString("'--fc-bg-animation'", $src);
		$this->assertStringContainsString("'--fc-bg-size'",      $src);
		$this->assertStringContainsString("'--fc-surface-backdrop'", $src);
	}

	public function testMockCardPaneUsesProductionClasses(): void
	{
		$src = $this->template();
		$this->assertStringContainsString('class="fc-cat-pro-list"', $src);
		$this->assertStringContainsString('class="fc-cat-pro-li"',   $src);
		$this->assertStringContainsString('class="fcpt-title"',      $src);
		$this->assertStringContainsString('class="fcpt-introtext"',  $src);
		$this->assertMatchesRegularExpression(
			'#data-fcpt-field-type="[a-z_]+"#',
			$src,
			'mock card must include at least one data-fcpt-field-type hook'
		);
	}

	public function testMockCardConsumesTokenStyle(): void
	{
		$this->assertMatchesRegularExpression(
			'#class="fc-cat-pro-list"\s+:style="tokenStyleString\(\)"#',
			$this->template(),
			'token preview container must bind :style to tokenStyleString()'
		);
	}

	public function testViewEnqueuesFrontendStylesheet(): void
	{
		$src = $this->view();
		$this->assertStringContainsString(
			'protemplate_frontend.css',
			$src,
			'admin view must enqueue the production frontend stylesheet'
		);
		$this->assertStringContainsString(
			'addStyleSheet',
			$src,
			'enqueue must use Document::addStyleSheet (Joomla convention)'
		);
	}
}
