<?php
/**
 * Guards the post-install "Run all pending" master button shipped in
 * 6.1.0-beta.4.
 *
 * The post-install panel (admin/views/flexicontent/tmpl/default_postinstall.php)
 * shows 17 install / migration tasks each with its own per-row "Update"
 * link that fires an AJAX endpoint. With fresh upgrades typically 4-7
 * tasks remain pending, requiring the operator to click each link by
 * hand. This patch adds a single "Run all pending" master button above
 * the table that chains every pending row's AJAX call sequentially
 * (using jQuery's ajaxStop event) and a live status region for AT.
 *
 * This test pins the contract so a future cleanup pass cannot silently
 * drop the master button, the live region, or the supporting i18n keys.
 *
 * A11y-cleared (accessibility-lead) — WCAG 4.1.3 (status live region),
 * 1.3.1 (button + role + aria-controls), 1.4.1 (no color-only signal).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.4
 */

namespace FLEXIcontent\Tests\Unit\PostInstall;

use PHPUnit\Framework\TestCase;

class RunAllPendingTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	private function templateSource(): string
	{
		$src = file_get_contents($this->root() . '/admin/views/flexicontent/tmpl/default_postinstall.php');
		$this->assertNotFalse($src, 'default_postinstall.php must be readable.');
		return $src;
	}

	public function testMasterRunAllButtonExists(): void
	{
		$src = $this->templateSource();

		$this->assertMatchesRegularExpression(
			'/<a\s+id="fc-runall-pending"[^>]*class="fc_button[^"]*"[^>]*role="button"/s',
			$src,
			'fc-runall-pending master button must be present with role="button" so it is exposed as a button to AT.'
		);

		$this->assertMatchesRegularExpression(
			'/aria-controls="fc-runall-status"/',
			$src,
			'fc-runall-pending must declare aria-controls pointing at the status live region.'
		);
	}

	public function testStatusLiveRegionExists(): void
	{
		$src = $this->templateSource();

		$this->assertMatchesRegularExpression(
			'/<span\s+id="fc-runall-status"[^>]*role="status"[^>]*aria-live="polite"[^>]*aria-atomic="true"/s',
			$src,
			'fc-runall-status must be a polite live region so chained task progress is announced to AT (WCAG 4.1.3).'
		);
	}

	public function testRunAllButtonHidesWhenNothingPending(): void
	{
		$src = $this->templateSource();

		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*\$_fc_pending_count\s*>\s*0\s*\)\s*:[\s\S]*?id="fc-runall-pending"/s',
			$src,
			'The master button block must be wrapped in `if ($_fc_pending_count > 0)` so it disappears when every task is OK.'
		);
	}

	public function testPendingFlagListCoversAllTrackedTasks(): void
	{
		$src = $this->templateSource();

		// All 17 boolean flags driving install-ok / install-notok rows
		// must be counted by the pending-count loop so the master button
		// number stays accurate.
		$requiredFlags = [
			'existfields', 'existcpfields', 'existmenuitems', 'existtype',
			'allplgpublish', 'existcats', 'langsynced', 'existdbindexes',
			'existversions', 'existversionsdata', 'existauthors', 'cachethumb',
			'itemcountingdok', 'deprecatedfiles', 'nooldfieldsdata',
			'missingversion', 'initialpermission',
		];

		foreach ($requiredFlags as $flag)
		{
			$this->assertStringContainsString(
				"'{$flag}'",
				$src,
				"Pending-count loop must include flag '{$flag}' so the master button label reflects the true pending total."
			);
		}
	}

	public function testChainedAjaxUsesAjaxStop(): void
	{
		$src = $this->templateSource();

		$this->assertMatchesRegularExpression(
			"/jQuery\(document\)\.ajaxStop\(/",
			$src,
			'Run-all chain must use jQuery ajaxStop so each task waits for its AJAX request to settle before advancing.'
		);

		$this->assertMatchesRegularExpression(
			"/\.trigger\(\s*['\"]click['\"]\s*\)/",
			$src,
			"Run-all chain must reuse each row's existing click handler via trigger('click') to avoid duplicating endpoint URLs."
		);
	}

	public function testI18nKeysExistInEnGB(): void
	{
		$ini = file_get_contents($this->root() . '/admin/language/en-GB/en-GB.com_flexicontent.ini');
		$this->assertNotFalse($ini, 'en-GB.com_flexicontent.ini must be readable.');

		$this->assertStringContainsString('FLEXI_RUN_ALL_PENDING_TASKS=', $ini);
		$this->assertStringContainsString('FLEXI_NO_PENDING_TASKS=', $ini);
		$this->assertStringContainsString('FLEXI_ALL_PENDING_TASKS_DONE=', $ini);
	}

	public function testI18nKeysExistInThTH(): void
	{
		$ini = file_get_contents($this->root() . '/admin/language/th-TH/th-TH.com_flexicontent.ini');
		$this->assertNotFalse($ini, 'th-TH.com_flexicontent.ini must be readable.');

		$this->assertStringContainsString('FLEXI_RUN_ALL_PENDING_TASKS=', $ini);
		$this->assertStringContainsString('FLEXI_NO_PENDING_TASKS=', $ini);
		$this->assertStringContainsString('FLEXI_ALL_PENDING_TASKS_DONE=', $ini);
	}
}
