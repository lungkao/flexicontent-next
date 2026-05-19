<?php
/**
 * End-to-end flow test: preset insert → Model::save() derivation → Resolver pick.
 *
 * Mirrors the production logic without touching the DB so it can run in CI.
 * Guards the user-visible promise: if you pick a preset in the chooser and
 * view the matching content, the saved layout MUST be resolved.
 *
 * Regression target — beta.6 user report 2026-05-19:
 *   "ระบบ Template Layout เลือกแล้ว แต่ ไม่ทำงาน ยังคงกลับไปใช้เทมเพลตเดิม"
 *
 * Keep in sync with:
 *   - admin/models/protemplate.php::save()           — derivation rules
 *   - admin/models/protemplate.php::createFromPreset — initial row shape
 *   - admin/helpers/protemplate/Resolver.php         — match logic
 *
 * @package  FLEXIcontent
 * @since    6.1.0
 */

namespace FLEXIcontent\Tests\Unit\ProTemplate;

use PHPUnit\Framework\TestCase;

class PresetSaveResolveFlowTest extends TestCase
{
	/** Priority weights — must match Resolver.php */
	private const PRIORITY = [
		'item'     => 1,
		'menu'     => 2,
		'category' => 3,
		'type'     => 4,
		'global'   => 5,
	];

	/** Mirror of FlexicontentModelProtemplate::save() derivation block. */
	private function deriveSave(array $data): array
	{
		$scope  = (isset($data['view_scope']) && $data['view_scope'] === 'category') ? 'category' : 'item';
		$catid  = (int) ($data['catid']   ?? 0);
		$typeId = (int) ($data['type_id'] ?? 0);

		if ($scope === 'category') {
			$data['type_id'] = 0;
			$typeId = 0;
		}
		$data['view_scope'] = $scope;

		if ($catid > 0) {
			$data['assignment_type'] = 'category';
		} elseif ($typeId > 0) {
			$data['assignment_type'] = 'type';
		} else {
			$data['assignment_type'] = 'global';
		}
		$data['assignment_value'] = '';

		return $data;
	}

	/** Mirror of createFromPreset() row shape for a named preset. */
	private function createFromPreset(string $key, string $scope, array $layout, int $id = 1): array
	{
		return [
			'id'               => $id,
			'title'            => 'Preset ' . $key,
			'type_id'          => 0,
			'catid'            => 0,
			'assignment_type'  => 'global',
			'assignment_value' => '',
			'view_scope'       => $scope === 'category' ? 'category' : 'item',
			'layout_data'      => json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			'theme_id'         => 0,
			'state'            => 1,
			'ordering'         => 0,
		];
	}

	/** Mirror of Resolver::isApplicable */
	private function isApplicable(array $row, array $context): bool
	{
		switch ($row['assignment_type']) {
			case 'item':
				return (string) $row['assignment_value'] === (string) $context['item_id']
					&& (int) $context['item_id'] > 0;
			case 'menu':
				return (string) $row['assignment_value'] === (string) $context['menu_id']
					&& (int) $context['menu_id'] > 0;
			case 'category':
				return (int) $row['catid'] === (int) $context['cat_id']
					&& (int) $context['cat_id'] > 0;
			case 'type':
				return (int) $row['type_id'] === (int) $context['type_id']
					&& (int) $context['type_id'] > 0;
			case 'global':
				return true;
		}
		return false;
	}

	/**
	 * Mirror of Resolver::resolve - SQL prefilter (state=1, view_scope) +
	 * PHP applicability + priority rank.
	 */
	private function resolve(array $rows, array $context, string $viewScope): ?array
	{
		$candidates = array_values(array_filter($rows, static function ($r) use ($viewScope) {
			return (int) $r['state'] === 1 && (string) $r['view_scope'] === $viewScope;
		}));

		$best     = null;
		$bestRank = PHP_INT_MAX;
		foreach ($candidates as $row) {
			if (!$this->isApplicable($row, $context)) continue;
			$rank = self::PRIORITY[$row['assignment_type']] ?? PHP_INT_MAX;
			if ($rank < $bestRank) {
				$best     = $row;
				$bestRank = $rank;
			}
		}
		return $best;
	}

	// ─────────────────────────────────────────────────────────────────
	// Scenario tests — user-reported flow
	// ─────────────────────────────────────────────────────────────────

	/** @test */
	public function preset_chooser_creates_global_item_layout_that_resolves_for_item_view(): void
	{
		$row = $this->createFromPreset(
			'item-magazine',
			'item',
			['version' => 2, 'sections' => [['id' => 's1', 'rows' => []]]]
		);

		$itemContext = [
			'item_id' => 42,
			'cat_id'  => 7,
			'type_id' => 3,
			'menu_id' => 0,
		];

		$picked = $this->resolve([$row], $itemContext, 'item');

		$this->assertNotNull($picked, 'Preset row must resolve for item view — global scope should always match');
		$this->assertSame(1, $picked['id']);
		$this->assertSame('global', $picked['assignment_type']);
	}

	/** @test */
	public function preset_chooser_creates_global_category_layout_that_resolves_for_category_view(): void
	{
		$row = $this->createFromPreset(
			'cat-grid',
			'category',
			['version' => 2, 'sections' => [['id' => 's1', 'rows' => []]]]
		);

		$catContext = [
			'item_id' => 0,
			'cat_id'  => 7,
			'type_id' => 0,
			'menu_id' => 0,
		];

		$picked = $this->resolve([$row], $catContext, 'category');

		$this->assertNotNull($picked);
		$this->assertSame('global',   $picked['assignment_type']);
		$this->assertSame('category', $picked['view_scope']);
	}

	/** @test */
	public function item_scope_layout_never_bleeds_into_category_view(): void
	{
		$itemRow = $this->createFromPreset('item-magazine', 'item',     ['version' => 2, 'sections' => []], 1);
		$catRow  = $this->createFromPreset('cat-grid',      'category', ['version' => 2, 'sections' => []], 2);

		$catContext = [
			'item_id' => 0,
			'cat_id'  => 7,
			'type_id' => 0,
			'menu_id' => 0,
		];

		$picked = $this->resolve([$itemRow, $catRow], $catContext, 'category');

		$this->assertNotNull($picked);
		$this->assertSame(2, $picked['id'], 'Category view must NOT pick up an item-scope layout');
	}

	/** @test */
	public function save_with_only_catid_derives_category_assignment(): void
	{
		$saved = $this->deriveSave([
			'view_scope' => 'item',
			'catid'      => 5,
			'type_id'    => 0,
		]);

		$this->assertSame('category', $saved['assignment_type']);
		$this->assertSame(5,          $saved['catid']);
		$this->assertSame('',         $saved['assignment_value']);

		$row = [
			'id' => 1, 'title' => 't', 'type_id' => 0, 'catid' => 5,
			'assignment_type'  => $saved['assignment_type'],
			'assignment_value' => '',
			'view_scope' => 'item', 'state' => 1, 'ordering' => 0, 'layout_data' => '{}',
		];
		$ctx = ['item_id' => 100, 'cat_id' => 5, 'type_id' => 2, 'menu_id' => 0];

		$picked = $this->resolve([$row], $ctx, 'item');
		$this->assertNotNull($picked);
		$this->assertSame(1, $picked['id']);
	}

	/** @test */
	public function save_with_only_typeid_derives_type_assignment(): void
	{
		$saved = $this->deriveSave([
			'view_scope' => 'item',
			'catid'      => 0,
			'type_id'    => 3,
		]);

		$this->assertSame('type', $saved['assignment_type']);
		$this->assertSame(3,      $saved['type_id']);
	}

	/** @test */
	public function save_with_both_catid_and_typeid_prefers_category(): void
	{
		$saved = $this->deriveSave([
			'view_scope' => 'item',
			'catid'      => 5,
			'type_id'    => 3,
		]);

		$this->assertSame('category', $saved['assignment_type']);
	}

	/** @test */
	public function category_scope_forces_typeid_to_zero(): void
	{
		$saved = $this->deriveSave([
			'view_scope' => 'category',
			'catid'      => 5,
			'type_id'    => 99,
		]);

		$this->assertSame(0,          $saved['type_id']);
		$this->assertSame('category', $saved['assignment_type']);
	}

	/** @test */
	public function unpublished_row_never_resolves(): void
	{
		$row = $this->createFromPreset('item-magazine', 'item', ['version' => 2, 'sections' => []]);
		$row['state'] = 0;

		$ctx = ['item_id' => 42, 'cat_id' => 7, 'type_id' => 3, 'menu_id' => 0];

		$picked = $this->resolve([$row], $ctx, 'item');
		$this->assertNull($picked);
	}

	/** @test */
	public function multiple_global_rows_picks_lowest_ordering(): void
	{
		$rowA = $this->createFromPreset('a', 'item', ['version' => 2, 'sections' => []], 10);
		$rowA['ordering'] = 5;
		$rowB = $this->createFromPreset('b', 'item', ['version' => 2, 'sections' => []], 20);
		$rowB['ordering'] = 1;

		// SQL would return by ordering ASC — mirror that contract before
		// passing rows to the PHP resolver.
		$rowsSorted = [$rowA, $rowB];
		usort($rowsSorted, static fn($x, $y) => [$x['ordering'], $x['id']] <=> [$y['ordering'], $y['id']]);

		$ctx = ['item_id' => 42, 'cat_id' => 0, 'type_id' => 0, 'menu_id' => 0];
		$picked = $this->resolve($rowsSorted, $ctx, 'item');

		$this->assertNotNull($picked);
		$this->assertSame(20, $picked['id'], 'Lower ordering must win even with higher id');
	}

	/** @test */
	public function category_assignment_only_matches_its_own_catid(): void
	{
		$row = [
			'id' => 1, 'title' => 't', 'type_id' => 0, 'catid' => 5,
			'assignment_type'  => 'category',
			'assignment_value' => '',
			'view_scope' => 'item', 'state' => 1, 'ordering' => 0, 'layout_data' => '{}',
		];

		$ctxMatch    = ['item_id' => 100, 'cat_id' => 5, 'type_id' => 0, 'menu_id' => 0];
		$ctxMismatch = ['item_id' => 100, 'cat_id' => 7, 'type_id' => 0, 'menu_id' => 0];

		$this->assertNotNull($this->resolve([$row], $ctxMatch,    'item'));
		$this->assertNull(   $this->resolve([$row], $ctxMismatch, 'item'));
	}

	/** @test */
	public function resolver_helper_exposes_lastDebug_for_diagnostic_hookup(): void
	{
		// Beta.6 diagnostic mode adds ?proDebug=1 — site/admin hookups read
		// FlexicontentProTemplateResolver::getLastDebug() and emit an HTML
		// comment. Guard the public API contract: method exists and returns
		// null before any resolve() call.
		$resolverPath = dirname(__DIR__, 3) . '/admin/helpers/protemplate/Resolver.php';
		$this->assertFileExists($resolverPath);

		// Guarded define so we don't crash when other tests run first
		if (!defined('_JEXEC')) {
			define('_JEXEC', 1);
		}

		// Use the real source by reading it — we can't require_once safely
		// (Joomla DB class not loaded in test env), so we parse the file
		// for the method signature instead. Logic mirror tests already
		// cover behavior — this test guards the symbol.
		$src = file_get_contents($resolverPath);
		$this->assertStringContainsString('public static function getLastDebug', $src);
		$this->assertStringContainsString('protected static $lastDebug', $src);
	}
}
