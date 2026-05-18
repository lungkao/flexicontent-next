<?php
/**
 * Guards the typography parity fix shipped in 6.1.0-beta.2.
 *
 * Regression history:
 *
 * 1. Joomla 1.5-era admin CSS forced legacy `font-family: arial`
 *    and `font-family: 'Roboto' !important` inside FLEXIcontent admin
 *    chrome. Roboto carries no Thai glyphs, so Thai content fell back
 *    to the browser default, producing a visible font swap when users
 *    entered any FLEXI view.
 *
 * 2. The first parity patch overscoped the reset to `body.com_flexicontent`
 *    (admin) / `.com_flexicontent` (frontend). That body-level selector
 *    cascaded into Joomla Atum sidebar + topbar chrome (which live OUTSIDE
 *    the `#flexicontent` wrapper) and reset their font-family to `inherit`
 *    -> user-agent default (serif Times), since the <html> element has no
 *    font-family declared.
 *
 * The fix narrows the parity scope to FLEXI-owned wrappers only:
 *    `#flexicontent`, `#j-main-container .fc_field`, `.fcfields`, `.fc_field`.
 *
 * This test pins both halves of the fix so a future cleanup pass cannot
 * silently re-introduce either failure mode.
 *
 * A11y-cleared (accessibility-lead) — WCAG 1.4.4 / 1.4.8 / 1.4.12 PASS,
 * WCAG 3.1.2 improved (Thai content now inherits Atum stack, no
 * Roboto fallback).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.2
 */

namespace FLEXIcontent\Tests\Unit\Typography;

use PHPUnit\Framework\TestCase;

class TypographyParityScopeTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	public function testJ4xCssDoesNotForceArialOrRoboto(): void
	{
		$j4x = $this->root() . '/admin/assets/css/j4x.css';
		$this->assertFileExists($j4x);
		$css = file_get_contents($j4x);

		$this->assertDoesNotMatchRegularExpression(
			'/font-family\s*:\s*arial\b/i',
			$css,
			'j4x.css must not force font-family: arial (legacy Joomla 1.5 leftover that overrides Atum stack).'
		);

		$this->assertDoesNotMatchRegularExpression(
			"/font-family\\s*:\\s*'?Roboto'?\\s*!important/i",
			$css,
			"j4x.css must not force 'Roboto' !important — Roboto has no Thai glyphs and causes font swap on Thai content."
		);
	}

	public function testAdminParityBlockIsScopedToFlexicontentWrapper(): void
	{
		$css = file_get_contents($this->root() . '/admin/assets/css/flexicontentbackend.css');
		$this->assertNotFalse($css);

		$this->assertDoesNotMatchRegularExpression(
			'/body\.com_flexicontent\s*\*/',
			$css,
			'Admin parity block must not target body.com_flexicontent * (cascades into Atum chrome).'
		);

		$this->assertDoesNotMatchRegularExpression(
			'/body\.com_flexicontent\s*,/',
			$css,
			'Admin parity block must not target body.com_flexicontent as a top-level selector.'
		);

		$this->assertStringContainsString(
			'#flexicontent *:not([class*="icon-"])',
			$css,
			'Admin parity block must use the scoped #flexicontent *:not(icons) selector.'
		);
	}

	public function testSiteParityBlockIsScopedToFlexicontentWrapper(): void
	{
		$css = file_get_contents($this->root() . '/site/assets/css/flexicontent.css');
		$this->assertNotFalse($css);

		$this->assertDoesNotMatchRegularExpression(
			'/Reset hardcoded font-family[^{]+\.com_flexicontent\s*,\s*\n\s*\.com_flexicontent input/s',
			$css,
			'Site parity block must not target .com_flexicontent as a wrapper selector.'
		);

		$this->assertStringContainsString(
			'#flexicontent input',
			$css,
			'Site parity block must use #flexicontent as its scoping wrapper.'
		);

		$this->assertStringContainsString(
			'html[lang^="th"] #flexicontent',
			$css,
			'Thai line-height boost must be scoped to #flexicontent, not .com_flexicontent.'
		);
	}

	public function testMinifiedCssIsRebuiltWithScopedSelectors(): void
	{
		$adminMin = file_get_contents($this->root() . '/admin/assets/css/flexicontentbackend.min.css');
		$this->assertNotFalse($adminMin);
		$this->assertStringNotContainsString(
			'body.com_flexicontent',
			$adminMin,
			'Admin min.css must be rebuilt — stale body.com_flexicontent selector still present.'
		);

		$siteMin = file_get_contents($this->root() . '/site/assets/css/flexicontent.min.css');
		$this->assertNotFalse($siteMin);
		$this->assertStringNotContainsString(
			"'Roboto'!important",
			$siteMin,
			"Site min.css must not contain forced Roboto."
		);
	}
}
