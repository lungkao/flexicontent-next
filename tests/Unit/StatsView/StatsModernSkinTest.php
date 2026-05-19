<?php
/**
 * Guards the /stats view modernization shipped in 6.1.0-beta.4.
 *
 * The /stats template is a 700+ line Bootstrap 2 legacy markup that we
 * deliberately did NOT rewrite. Instead a small CSS skin
 * (admin/assets/css/stats_modern.css) retro-fits the existing markup
 * to FC design tokens, and a handful of template patches add the
 * accessibility hooks the skin needs:
 *
 *   - inline `style="font-size:18px;..."` removed from every
 *     `.fc-stats-heading` and replaced by stable section IDs;
 *   - each echart canvas gains `role="img"` + `aria-labelledby` +
 *     `aria-describedby` plus a visually-hidden `<table class="fc-chart-data">`
 *     sibling carrying the same data (WCAG 1.1.1).
 *
 * This test pins the contract so a future "clean-up" pass cannot silently
 * drop the a11y hooks or re-introduce the inline heading styles.
 *
 * A11y-cleared (accessibility-lead) — WCAG 1.1.1 (chart data table
 * alternative), 1.4.1 (color enhancement only), 2.4.7 (focus-visible).
 *
 * @package  FLEXIcontent
 * @since    6.1.0-beta.4
 */

namespace FLEXIcontent\Tests\Unit\StatsView;

use PHPUnit\Framework\TestCase;

class StatsModernSkinTest extends TestCase
{
	private function root(): string
	{
		return dirname(__DIR__, 3);
	}

	private function templateSource(): string
	{
		$src = file_get_contents($this->root() . '/admin/views/stats/tmpl/default.php');
		$this->assertNotFalse($src, 'stats template must be readable.');
		return $src;
	}

	private function viewSource(): string
	{
		$src = file_get_contents($this->root() . '/admin/views/stats/view.html.php');
		$this->assertNotFalse($src, 'stats view.html.php must be readable.');
		return $src;
	}

	public function testStatsModernSkinFileExists(): void
	{
		$css = $this->root() . '/admin/assets/css/stats_modern.css';
		$this->assertFileExists($css, 'admin/assets/css/stats_modern.css must exist.');

		$content = file_get_contents($css);
		$this->assertStringContainsString('#flexicontent .fc-stats-heading', $content);
		$this->assertStringContainsString('.fc-chart-data', $content);
		$this->assertStringContainsString(':focus-visible', $content);
		$this->assertStringContainsString('prefers-reduced-motion', $content);
	}

	public function testViewRegistersStatsModernStyle(): void
	{
		$src = $this->viewSource();

		$this->assertMatchesRegularExpression(
			"/registerAndUseStyle\(\s*'fc-stats-modern'/",
			$src,
			"view.html.php must register the 'fc-stats-modern' WAM style asset."
		);

		$this->assertStringContainsString(
			'stats_modern.css',
			$src,
			'view.html.php must reference the stats_modern.css path.'
		);

		// J5/J6 guard so legacy J3 path is not affected.
		$this->assertMatchesRegularExpression(
			"/if\s*\(\s*FLEXI_J40GE\s*\)[^}]*registerAndUseStyle\(\s*'fc-stats-modern'/s",
			$src,
			"fc-stats-modern registration must be guarded by FLEXI_J40GE so legacy J3 admin is untouched."
		);
	}

	public function testNoInlineHeadingStyleRemainsInTemplate(): void
	{
		$src = $this->templateSource();

		$this->assertDoesNotMatchRegularExpression(
			'/class="fc-stats-heading"\s+style=/',
			$src,
			'<h2 class="fc-stats-heading"> elements must not carry inline style attributes — move presentation to stats_modern.css.'
		);
	}

	public function testSectionHeadingsHaveStableIds(): void
	{
		$src = $this->templateSource();

		$expectedIds = [
			'fc-stats-site-totals',
			'fc-stats-items-creation',
			'fc-stats-item-states',
			'fc-stats-general',
			'fc-stats-rating',
			'fc-stats-users',
		];

		foreach ($expectedIds as $id)
		{
			$this->assertMatchesRegularExpression(
				'/<h2[^>]*class="fc-stats-heading"[^>]*id="' . preg_quote($id, '/') . '"/',
				$src,
				"Section heading id=\"{$id}\" must exist so chart aria-labelledby targets remain stable."
			);
		}
	}

	public function testEchartCanvasesHaveAriaRoleAndDataTable(): void
	{
		$src = $this->templateSource();

		// items-by-month bar chart
		$this->assertMatchesRegularExpression(
			'/<div\s+id="main"[^>]*role="img"[^>]*aria-labelledby="fc-stats-items-creation"[^>]*aria-describedby="fc-chart-items-creation-data"/s',
			$src,
			'#main echart canvas must declare role=img + aria-labelledby + aria-describedby.'
		);

		$this->assertMatchesRegularExpression(
			'/<table[^>]*class="fc-chart-data"[^>]*id="fc-chart-items-creation-data"/',
			$src,
			'Visually-hidden data table for items-creation chart must exist (WCAG 1.1.1).'
		);

		// item-states pie chart
		$this->assertMatchesRegularExpression(
			'/<div\s+id="pie"[^>]*role="img"[^>]*aria-labelledby="fc-stats-item-states"[^>]*aria-describedby="fc-chart-item-states-data"/s',
			$src,
			'#pie echart canvas must declare role=img + aria-labelledby + aria-describedby.'
		);

		$this->assertMatchesRegularExpression(
			'/<table[^>]*class="fc-chart-data"[^>]*id="fc-chart-item-states-data"/',
			$src,
			'Visually-hidden data table for item-states chart must exist (WCAG 1.1.1).'
		);
	}

	public function testChartDataTablesUseScopedHeaders(): void
	{
		$src = $this->templateSource();

		// Both data tables should declare scope="col" header cells so AT
		// announces column context, and scope="row" for the date/label key.
		$this->assertMatchesRegularExpression(
			'/<table[^>]*class="fc-chart-data"[^>]*>[\s\S]*?<th\s+scope="col"[\s\S]*?<th\s+scope="row"/s',
			$src,
			'fc-chart-data tables must use scope="col" + scope="row" for proper AT navigation.'
		);
	}
}
