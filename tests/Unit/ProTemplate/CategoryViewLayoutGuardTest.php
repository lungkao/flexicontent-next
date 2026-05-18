<?php
/**
 * Unit tests for the Pro Templates layout-whitelist guard inside the
 * frontend category view (site/views/category/view.html.php).
 *
 * Pro Templates adapts a single category into an item-shape source
 * (title/introtext/image). That only makes sense for the default
 * single-category render. Multi-source list layouts — 'mcats', 'tags',
 * 'favs', 'author', 'myitems' — have no usable $this->category, so
 * the renderer would emit empty output and short-circuit the legacy
 * fallback. The view guards against this with a whitelist that allows
 * only '' (default) and 'category'.
 *
 * These tests mirror that whitelist logic and assert the same guard
 * predicate also exists in the production view file via source-text
 * inspection (so an accidental removal during a refactor fails CI).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.2
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class CategoryViewLayoutGuardTest extends TestCase
{
	private const WHITELIST = ['', 'category'];

	private const BLOCKED   = ['mcats', 'tags', 'favs', 'author', 'myitems'];

	/** Mirror of the whitelist predicate inlined in view.html.php. */
	private function proAllowed(string $urlLayout): bool
	{
		return in_array($urlLayout, self::WHITELIST, true);
	}

	public function testDefaultLayoutAllowsProTemplates(): void
	{
		$this->assertTrue($this->proAllowed(''),        'empty layout = default single-category render');
		$this->assertTrue($this->proAllowed('category'), 'explicit category layout = single-category render');
	}

	public function testMultiSourceLayoutsBlockProTemplates(): void
	{
		foreach (self::BLOCKED as $layout) {
			$this->assertFalse(
				$this->proAllowed($layout),
				"layout '{$layout}' must be blocked — multi-source view, no usable single category"
			);
		}
	}

	public function testUnknownLayoutDefaultsToBlocked(): void
	{
		$this->assertFalse($this->proAllowed('unknown-future-layout'));
		$this->assertFalse($this->proAllowed('item'));
	}

	/**
	 * Regression guard: the production view must keep the whitelist
	 * predicate in place. A refactor that removes the guard would
	 * re-introduce the empty-page bug at /?option=com_flexicontent&
	 * view=category&layout=mcats&...
	 */
	public function testProductionViewContainsLayoutWhitelistGuard(): void
	{
		$view = dirname(__DIR__, 3) . '/site/views/category/view.html.php';
		$this->assertFileExists($view);

		$src = file_get_contents($view);
		$this->assertNotFalse($src);

		// Must read layout from URL before invoking the resolver
		$this->assertStringContainsString(
			"\$jinput->getCmd('layout'",
			$src,
			'view must read URL layout for the Pro Templates whitelist guard'
		);

		// Must whitelist '' + 'category' (default-deny everything else)
		$this->assertMatchesRegularExpression(
			"/in_array\\(\\s*\\\$_proUrlLayout\\s*,\\s*\\[\\s*''\\s*,\\s*'category'\\s*\\]\\s*,\\s*true\\s*\\)/",
			$src,
			"view must whitelist ['', 'category'] before invoking Pro Templates resolver"
		);
	}
}
