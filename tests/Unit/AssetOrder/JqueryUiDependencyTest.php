<?php
/**
 * Guards the jQuery UI CDN load-order fix for Joomla 5/6 admin.
 *
 * Regression history:
 *
 * 1. FLEXIcontent registered `jquery-ui` from code.jquery.com CDN with no
 *    declared dependency on Joomla's `jquery-noconflict`. Joomla 5/6's
 *    WebAssetManager could emit the CDN bundle BEFORE Joomla core jQuery,
 *    causing a `ReferenceError: jQuery is not defined` at script eval.
 *
 * 2. The thrown ReferenceError broke downstream Atum admin assets that
 *    init on DOM ready — MetisMenu sidebar (`$.fn.metisMenu`) and the
 *    keyboard `hotkeys` keymap would silently fail to bind.
 *
 * 3. Fix declares an explicit WAM dependency on `jquery-noconflict` in
 *    BOTH admin entry (`admin/admin.flexicontent.php`) and the shared
 *    helper (`site/classes/helpers/html.php::loadJQuery`). The dependency
 *    array is the 5th argument to `registerAndUseScript()`.
 *
 * This test pins both call sites so a future cleanup pass cannot silently
 * drop the dependency and reintroduce the MetisMenu/hotkeys breakage.
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.4
 */

namespace FLEXIcontent\Tests\Unit\AssetOrder;

use PHPUnit\Framework\TestCase;

class JqueryUiDependencyTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	private function adminEntrySource(): string
	{
		$src = file_get_contents($this->root() . '/admin/admin.flexicontent.php');
		$this->assertNotFalse($src, 'admin.flexicontent.php must be readable.');
		return $src;
	}

	private function htmlHelperSource(): string
	{
		$src = file_get_contents($this->root() . '/site/classes/helpers/html.php');
		$this->assertNotFalse($src, 'site/classes/helpers/html.php must be readable.');
		return $src;
	}

	/**
	 * Asserts that the registerAndUseScript('jquery-ui', ...) block in $src
	 * declares 'jquery-noconflict' as a dependency.
	 *
	 * Matches the canonical 5-arg form used by Joomla 5/6 WAM:
	 *   registerAndUseScript($key, $uri, $options, $attribs, $dependencies)
	 */
	private function assertJqueryUiHasNoconflictDep(string $src, string $context): void
	{
		$pattern = "/registerAndUseScript\s*\(\s*['\"]jquery-ui['\"][^;]*['\"]jquery-noconflict['\"]/s";

		$this->assertMatchesRegularExpression(
			$pattern,
			$src,
			"{$context}: registerAndUseScript('jquery-ui', ...) must declare 'jquery-noconflict' as a WAM dependency so Joomla core jQuery loads first. Without this, jQuery UI CDN may evaluate before jQuery is defined and break MetisMenu / hotkeys init."
		);
	}

	public function testAdminEntryDeclaresJqueryNoconflictDependency(): void
	{
		$this->assertJqueryUiHasNoconflictDep(
			$this->adminEntrySource(),
			'admin/admin.flexicontent.php'
		);
	}

	public function testLoadJqueryHelperDeclaresJqueryNoconflictDependency(): void
	{
		$this->assertJqueryUiHasNoconflictDep(
			$this->htmlHelperSource(),
			'site/classes/helpers/html.php (loadJQuery)'
		);
	}

	public function testJqueryUiCdnVersionMatchesAcrossCallSites(): void
	{
		// Both call sites should pin the same jQuery UI version so the
		// `jquery-ui` WAM key never resolves to two different URIs.
		$adminSrc = $this->adminEntrySource();
		$helperSrc = $this->htmlHelperSource();

		$this->assertSame(
			1,
			preg_match("/code\.jquery\.com\/ui\/([0-9]+\.[0-9]+\.[0-9]+)\/jquery-ui\.min\.js/", $adminSrc, $a),
			'admin entry must reference a versioned jquery-ui CDN URL.'
		);
		$this->assertSame(
			1,
			preg_match("/code\.jquery\.com\/ui\/([0-9]+\.[0-9]+\.[0-9]+)\/jquery-ui\.min\.js/", $helperSrc, $b),
			'loadJQuery helper must reference a versioned jquery-ui CDN URL.'
		);

		$this->assertSame(
			$a[1],
			$b[1],
			'Admin entry and loadJQuery helper must pin the same jQuery UI CDN version to avoid WAM key collisions.'
		);
	}
}
